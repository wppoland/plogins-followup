<?php

declare(strict_types=1);

namespace Followup\Service;

use Followup\Contract\HasHooks;
use Followup\Service\SequenceSteps;

defined('ABSPATH') || exit;

/**
 * Daily wp-cron worker. For every enabled follow-up type it finds orders that
 *
 *  1. reached the configured trigger status, and
 *  2. have been in (or past) that status for at least `delay` days, and
 *  3. have not already been sent this follow-up,
 *
 * then sends one email per order via {@see Mailer}.
 *
 * The order is claimed before the message is handed to wp_mail, not after, so
 * a run that dies mid-send cannot leave an order looking unsent. That choice
 * trades silent loss for the possibility of a duplicate, and the duplicate is
 * bounded: a claim that no run ever finished is retried exactly once and then
 * left alone. So a follow-up is sent once per order, with two ways a customer
 * can see it twice, both of which need a send to die after the mail server has
 * already taken the message: the one retry that claim gets, and two cron runs
 * overlapping in the moment between one claiming an order and the other
 * reading it.
 *
 * Two bounds keep the mailing sane. Orders placed before the sender started
 * running on this site are never followed up, so switching it on in a shop
 * with years of orders behind it does not mail those customers; and each type
 * sends at most BATCH_LIMIT emails per run.
 *
 * That second ceiling counts emails, not orders read. An order that is read
 * and cannot send, because an add-on holds it back or wp_mail refuses it,
 * spends none of it: the step reads on past that order in the same run, up to
 * SCAN_LIMIT orders. A run the read ceiling cuts short records where it
 * stopped, so the next one starts there instead of reading the same wall again
 * and sending nothing, for ever. A run that instead reaches the end of the
 * step's queue records nothing and the next one starts at the top, which is
 * what offers an order held back earlier another chance.
 *
 * All of that assumes High-Performance Order Storage. On the legacy posts
 * table wc_get_orders drops 'meta_query' (WC_Data_Store_WP::get_wp_query_args
 * skips the key, and WooCommerce says so through wc_doing_it_wrong), so both
 * meta filters below are no-ops there and every query returns plain due
 * orders, already-sent ones included. Nothing is mailed twice, because the
 * claim is re-read per order in deliver(), and nothing is lost. The cost is
 * latency: the paging has to walk the shop's history, about 800 orders a run,
 * before it reaches the orders that are actually due. Measured against the
 * fake store in tests/: 3,000 already-sent orders plus one due order mails the
 * due one on the fourth run, 30,000 on the thirty-eighth. Enabling HPOS
 * removes it.
 */
final class Scheduler implements HasHooks
{
    public const CRON_HOOK = 'followup_daily_event';

    /** Per-order meta prefix recording when a follow-up was sent. */
    private const META_PREFIX = '_followup_sent_';

    /**
     * Maximum emails sent per type per run, to keep cron bounded. Public
     * because the settings screen states the ceiling to the merchant, and a
     * number stated in two places drifts.
     */
    public const BATCH_LIMIT = 200;

    /**
     * Maximum orders read per type per run. The send ceiling cannot bound the
     * work on its own, because an order that is read and not sent costs a row
     * and buys nothing: without this, a step in front of ten thousand orders
     * it may not send would read all ten thousand looking for one it may.
     */
    private const SCAN_LIMIT = 1000;

    /**
     * Option holding the UTC timestamp before which an order is never followed
     * up. Activation records the install moment, so the first run mails the
     * orders placed from then on and not the shop's entire order history.
     */
    public const FLOOR_OPTION = 'followup_install_floor';

    /**
     * Option holding the UTC timestamp of the most recent activation that was
     * not the first one on this site. Written by the activation hook and read
     * by every run after it, which is where the configured delays are known.
     *
     * It is kept rather than spent, because the reach-back it buys is per step.
     * Folding it into the stored floor means picking one delay for every step,
     * and the only safe pick is the longest, which is how a 30 day review step
     * used to hand the thank-you 30 days of orders to thank people for. A
     * later activation overwrites it, so it only ever moves forward.
     */
    public const REACTIVATED_OPTION = 'followup_reactivated_at';

    /**
     * Option holding, per step id, how far into that step's due orders a run
     * got before the read ceiling stopped it. Only a run cut short that way
     * writes one. A run that reads the step's queue to its end clears it
     * instead, whether or not it managed to send anything, so the next run
     * starts at the top and the orders it could not send are offered again.
     * Absent on a site where every order that came up was sent, which is the
     * ordinary case.
     */
    public const SCAN_OPTION = 'followup_scan_offset';

    /** Meta value written while a send is in flight, before wp_mail returns. */
    private const CLAIM_PENDING = 'pending';

    /** Meta value written on the one retry a crashed send gets. */
    private const CLAIM_GAVE_UP = 'failed';

    public function __construct(
        private readonly SequenceSteps $sequenceSteps,
        private readonly Mailer $mailer,
    ) {
    }

    public function registerHooks(): void
    {
        add_action(self::CRON_HOOK, [$this, 'run']);

        // Self-heal: if the event was lost (e.g. plugin updated without
        // re-activation), reschedule it on the next admin load.
        add_action('admin_init', [$this, 'ensureScheduled']);
    }

    /**
     * Ensure the daily event is scheduled. Cheap and idempotent.
     */
    public function ensureScheduled(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Cron entry point. Processes every enabled follow-up type.
     *
     * This runs on wp-cron, so it only fires when the site is visited, and on a
     * site with DISABLE_WP_CRON set and no system cron replacing it, it never
     * fires at all.
     */
    public function run(): void
    {
        if (! function_exists('wc_get_orders')) {
            return;
        }

        $steps   = $this->sequenceSteps->resolve();
        $floor   = $this->floor();
        $resumed = $this->resumedAt();

        foreach ($steps as $step) {
            if (empty($step['enabled'])) {
                continue;
            }

            $this->processStep($step, $floor, $resumed);
        }
    }

    /**
     * The install floor: the timestamp before which no step ever reaches,
     * whatever its delay.
     *
     * Two ways a site gets one, and it only ever moves forward:
     *
     *  - First activation records the install moment, in the activation hook.
     *  - A site with no floor at all has never run the sender. Core fires the
     *    activation hook once, on the blog the click happened on, so on a
     *    network activation that is every other site. Its floor is this moment,
     *    which is the only value that cannot reach back over orders the shop
     *    took before the plugin arrived.
     *
     * A re-activation raises it further, per step and not here: see
     * {@see self::REACTIVATED_OPTION} and {@see self::processStep()}.
     */
    private function floor(): int
    {
        $stored = get_option(self::FLOOR_OPTION, '');
        $floor  = is_numeric($stored) ? (int) $stored : 0;

        if ($floor <= 0) {
            $floor = time();
            add_option(self::FLOOR_OPTION, (string) $floor, '', false);

            // This moment is already stricter than anything a re-activation
            // could ask for, so there is nothing left for one to say.
            delete_option(self::REACTIVATED_OPTION);
        }

        return $floor;
    }

    /**
     * The moment the plugin was last switched back on, or 0 if it never was.
     */
    private function resumedAt(): int
    {
        $stored = get_option(self::REACTIVATED_OPTION, '');

        return is_numeric($stored) ? max(0, (int) $stored) : 0;
    }

    /**
     * Find and send one sequence step to all currently-due orders.
     *
     * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step
     * @param int $floor   Orders created before this timestamp are never followed up.
     * @param int $resumed The last re-activation, or 0 if the plugin was never switched back on.
     * @return int Emails sent.
     */
    private function processStep(array $step, int $floor, int $resumed): int
    {
        $type   = sanitize_key((string) ($step['id'] ?? ''));
        $status = sanitize_key((string) ($step['status'] ?? 'completed'));
        $delay  = max(0, absint($step['delay'] ?? 0));

        if ('' === $type) {
            return 0;
        }

        // A re-activation reaches back by this step's own delay. Nothing went
        // out while the plugin was off, so an order still inside this step's
        // window is followed up and everything older is left alone. Taking the
        // longest delay any step configures instead, which is what a single
        // stored floor forces, thanks people for month-old orders as soon as a
        // month-long step exists anywhere in the sequence.
        if ($resumed > 0) {
            $floor = max($floor, $resumed - ($delay * DAY_IN_SECONDS));
        }

        // Only orders modified on/before this cutoff are old enough. We use the
        // order's last-modified date as a proxy for "entered status N days ago";
        // it is conservative (never sends earlier than the delay). A timestamp,
        // not a date string: WooCommerce reads a 'Y-m-d H:i:s' string at day
        // precision, which let a follow-up go out up to a day early.
        $before = time() - ($delay * DAY_IN_SECONDS);

        $metaKey = self::META_PREFIX . sanitize_key($type);

        // What makes an order due, whether or not a claim is already on it. A
        // retry is the same order this step would pick today, so it is bounded
        // by exactly the same things: an order that has left the trigger status
        // since it was claimed, or that sits below the install floor, is not
        // mailed just because a crash left a claim behind on it.
        $due = [
            'status'        => $status,
            // Paging by row number needs a total order, not just a sort. Orders
            // that share a modified second, which a bulk status change gives a
            // whole screen of them at once, have no guaranteed order between
            // two queries, so a page boundary inside such a group can hand out
            // one order twice and skip another. The id breaks every tie: HPOS
            // maps both keys to columns, and on the posts table WP_Query reads
            // the same space-separated list.
            'orderby'       => 'modified ID',
            'order'         => 'ASC',
            'date_created'  => '>=' . $floor,
            'date_modified' => '<=' . $before,
        ];

        // Claims whose send never returned (a fatal or max_execution_time
        // mid-wp_mail). They are picked up first and retried once; without this
        // the claim written before the send would bury them for good. They
        // cannot pile up the way unclaimed orders can: every path out of a
        // claim either finishes it or drops it, so this set drains itself.
        $claimed = $this->query(array_merge($due, [
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an equality match on an indexed meta key, bounded by status, date and the batch limit.
            'meta_query' => [
                [
                    'key'   => $metaKey,
                    'value' => self::CLAIM_PENDING,
                ],
            ],
            'limit' => self::BATCH_LIMIT,
        ]));

        $sent = $this->deliver($claimed, $step, $metaKey);
        $read = count($claimed);

        // Where the last run stopped reading. Orders that were read and could
        // not be sent are still sitting in front of everything behind them, so
        // without this a step whose queue opens with a wall of held-back or
        // undeliverable orders reads that same wall on every run and never gets
        // past it, however many orders behind it are due.
        $offset = $this->scanOffset($type);

        while ($sent < self::BATCH_LIMIT && $read < self::SCAN_LIMIT) {
            $limit = min(self::BATCH_LIMIT - $sent, self::SCAN_LIMIT - $read);

            $page = $this->query(array_merge($due, [
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by status + date + the page limit; the "not sent" flag must be queried.
                'meta_query' => [
                    [
                        'key'     => $metaKey,
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                'limit'  => $limit,
                'offset' => $offset,
            ]));

            $read     += count($page);
            $delivered = $this->deliver($page, $step, $metaKey);
            $sent     += $delivered;

            if (count($page) === $limit) {
                // Paging by row number over a set the sending mutates, which
                // holds only because of what the mutation is: an order that was
                // sent leaves this query, since it now has the meta key, and an
                // order that could not be sent stays. So what is still in front
                // of the next page is exactly what this page could not send.
                $offset += count($page) - $delivered;
                continue;
            }

            // Short page: the end of this step's queue, including the empty one
            // a stale offset reads when the queue has since shrunk. Start the
            // next run at the top, so an order held back earlier in this pass
            // is offered again rather than skipped for good, and so a stale
            // offset costs one run rather than standing for ever.
            $offset = 0;
            break;
        }

        $this->rememberScanOffset($type, $offset);

        return $sent;
    }

    /**
     * Send one page of candidate orders and report how many went out.
     *
     * @param array<int, \WC_Order> $orders  Candidates, oldest first.
     * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step
     * @param string $metaKey Per-order meta key recording this step's send.
     * @return int Emails sent.
     */
    private function deliver(array $orders, array $step, string $metaKey): int
    {
        $sent = 0;
        foreach ($orders as $order) {
            // Re-read rather than trust the query: a batch is minutes old by
            // the time it is walked, and an order in it may have been finished
            // since. This does not make two overlapping runs safe. Reading a
            // claim and writing one are two statements, so a run that reads an
            // order before another run claims it still sends.
            $claim = (string) $order->get_meta($metaKey);

            // Anything other than an unfinished claim means this order is done
            // with: it was sent, or it was retried once and still did not
            // finish.
            if ('' !== $claim && self::CLAIM_PENDING !== $claim) {
                continue;
            }

            /**
             * Gate whether a follow-up should send on this cron run.
             *
             * @param bool     $send  Whether to send (default true).
             * @param \WC_Order $order The candidate order.
             * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step The sequence step.
             */
            if (! apply_filters('followup/should_send', true, $order, $step)) {
                // A veto is "not now", not "never", so an unfinished claim is
                // released rather than left standing. Nothing else ages a claim
                // out, and a vetoed claim that stayed would be read again on
                // every run for ever, out of a set that nothing drains.
                if ('' !== $claim) {
                    $order->delete_meta_data($metaKey);
                    $order->save_meta_data();
                }
                continue;
            }

            // Claim the order before sending, so a crash mid-send cannot mail
            // the same customer again on every run that follows. The claim
            // records which attempt this is, so an attempt that never returns
            // is retried exactly once and then left alone; the send timestamp
            // is written once wp_mail accepts it.
            $order->update_meta_data($metaKey, '' === $claim ? self::CLAIM_PENDING : self::CLAIM_GAVE_UP);
            $order->save_meta_data();

            if ($this->mailer->send($order, $step)) {
                $order->update_meta_data($metaKey, gmdate('c'));
                $order->save_meta_data();
                ++$sent;
                continue;
            }

            // wp_mail returned false: it refused the message (no address, empty
            // template) or the transport did (SMTP down). Either way nothing
            // was sent, on this attempt or the one before it, so the claim is
            // rolled back and the next run tries again. Only a send that never
            // returns at all leaves a claim standing, and that is the one the
            // retry above is for.
            $order->delete_meta_data($metaKey);
            $order->save_meta_data();
        }

        return $sent;
    }

    /**
     * How far into this step's due orders the last run got without sending.
     */
    private function scanOffset(string $type): int
    {
        $stored = get_option(self::SCAN_OPTION, []);

        if (! is_array($stored) || ! isset($stored[ $type ])) {
            return 0;
        }

        return max(0, (int) $stored[ $type ]);
    }

    /**
     * Record where this run stopped, or clear the record when the step read its
     * queue to the end.
     *
     * Two cron runs overlapping can both read the same starting offset and both
     * write, and the later write wins. That costs a page read twice, or skipped
     * until the next pass over the queue; it cannot send anything twice,
     * because what may be sent is claimed on the order itself, not decided here.
     */
    private function rememberScanOffset(string $type, int $offset): void
    {
        $stored  = get_option(self::SCAN_OPTION, []);
        $stored  = is_array($stored) ? $stored : [];
        $current = isset($stored[ $type ]) ? (int) $stored[ $type ] : 0;

        if ($current === $offset) {
            return;
        }

        if ($offset > 0) {
            $stored[ $type ] = $offset;
        } else {
            unset($stored[ $type ]);
        }

        update_option(self::SCAN_OPTION, $stored, false);
    }

    /**
     * Run one order query and return only the orders.
     *
     * @param array<string, mixed> $args
     * @return array<int, \WC_Order>
     */
    private function query(array $args): array
    {
        $orders = wc_get_orders(array_merge(
            [
                'type'   => 'shop_order',
                'return' => 'objects',
            ],
            $args,
        ));

        if (! is_array($orders)) {
            return [];
        }

        return array_values(array_filter(
            $orders,
            static fn ($order): bool => $order instanceof \WC_Order,
        ));
    }
}
