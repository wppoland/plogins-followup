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
 */
final class Scheduler implements HasHooks
{
    public const CRON_HOOK = 'followup_daily_event';

    /** Per-order meta prefix recording when a follow-up was sent. */
    private const META_PREFIX = '_followup_sent_';

    /**
     * Maximum orders processed per type per run, to keep cron bounded. Public
     * because the settings screen states the ceiling to the merchant, and a
     * number stated in two places drifts.
     */
    public const BATCH_LIMIT = 200;

    /**
     * Option holding the UTC timestamp before which an order is never followed
     * up. Activation records the install moment, so the first run mails the
     * orders placed from then on and not the shop's entire order history.
     */
    public const FLOOR_OPTION = 'followup_install_floor';

    /**
     * Option holding the UTC timestamp of an activation that was not the first
     * one on this site. Written by the activation hook and consumed by the next
     * run, which is where the configured delays are known.
     */
    public const REACTIVATED_OPTION = 'followup_reactivated_at';

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

        $steps = $this->sequenceSteps->resolve();
        $floor = $this->floor($steps);

        foreach ($steps as $step) {
            if (empty($step['enabled'])) {
                continue;
            }

            $this->processStep($step, $floor);
        }
    }

    /**
     * The timestamp before which an order is never followed up.
     *
     * Three ways a site gets one, and the floor only ever moves forward:
     *
     *  - First activation records the install moment, in the activation hook.
     *  - A site with no floor at all has never run the sender. Core fires the
     *    activation hook once, on the blog the click happened on, so on a
     *    network activation that is every other site. Its floor is this moment,
     *    which is the only value that cannot reach back over orders the shop
     *    took before the plugin arrived.
     *  - A re-activation moves the floor up to the start of the longest window
     *    any step configures. Nothing sent while the plugin was off, so without
     *    this the first run after a year away mails a year of orders; with it,
     *    the orders still inside their window are kept and the rest are not.
     *    Anything older than that window had already been mailed, or missed its
     *    moment by more than the delay the merchant chose.
     *
     * @param array<int, array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}> $steps
     */
    private function floor(array $steps): int
    {
        $stored = get_option(self::FLOOR_OPTION, '');
        $floor  = is_numeric($stored) ? (int) $stored : 0;

        if ($floor <= 0) {
            $floor = time();
            add_option(self::FLOOR_OPTION, (string) $floor, '', false);
            delete_option(self::REACTIVATED_OPTION);

            return $floor;
        }

        $reactivated = get_option(self::REACTIVATED_OPTION, '');

        if (is_numeric($reactivated) && (int) $reactivated > 0) {
            $window = 0;
            foreach ($steps as $step) {
                $window = max($window, max(0, absint($step['delay'] ?? 0)));
            }

            $floor = max($floor, (int) $reactivated - ($window * DAY_IN_SECONDS));

            update_option(self::FLOOR_OPTION, (string) $floor, false);
            delete_option(self::REACTIVATED_OPTION);
        }

        return $floor;
    }

    /**
     * Find and send one sequence step to all currently-due orders.
     *
     * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step
     * @param int $floor Orders created before this timestamp are never followed up.
     */
    private function processStep(array $step, int $floor): int
    {
        $type   = sanitize_key((string) ($step['id'] ?? ''));
        $status = sanitize_key((string) ($step['status'] ?? 'completed'));
        $delay  = max(0, absint($step['delay'] ?? 0));

        if ('' === $type) {
            return 0;
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
            'orderby'       => 'modified',
            'order'         => 'ASC',
            'date_created'  => '>=' . $floor,
            'date_modified' => '<=' . $before,
        ];

        // Claims whose send never returned (a fatal or max_execution_time
        // mid-wp_mail). They are picked up first and retried once; without this
        // the claim written before the send would bury them for good.
        $orders = $this->query(array_merge($due, [
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an equality match on an indexed meta key, bounded by status, date and the batch limit.
            'meta_query' => [
                [
                    'key'   => $metaKey,
                    'value' => self::CLAIM_PENDING,
                ],
            ],
            'limit' => self::BATCH_LIMIT,
        ]));

        $remaining = self::BATCH_LIMIT - count($orders);

        if ($remaining > 0) {
            $orders = array_merge($orders, $this->query(array_merge($due, [
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by status + date + batch limit; the "not sent" flag must be queried.
                'meta_query' => [
                    [
                        'key'     => $metaKey,
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                'limit' => $remaining,
            ])));
        }

        if ([] === $orders) {
            return 0;
        }

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
                // out: a vetoed one that stayed would be re-read out of the
                // same batch budget on every run, and enough of them would fill
                // it, so no order that is actually due would ever be reached.
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
