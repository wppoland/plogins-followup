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
 * then sends one email per order via {@see Mailer}. Idempotency is tracked with
 * a per-order meta flag (`_followup_sent_{type}`) so the same follow-up is never
 * sent twice, even across overlapping cron runs.
 *
 * Two bounds keep the mailing sane. Orders placed before the plugin was
 * installed are never followed up, so activating on a shop with years of orders
 * behind it does not mail those customers; and each type sends at most
 * BATCH_LIMIT emails per run.
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
     * Activation records the install moment. An install that predates the
     * option seeds it here instead, at the longest delay any step configures,
     * which keeps the follow-ups that are still legitimately pending and leaves
     * the rest of the order history alone. Seeding it lazily also covers the
     * site whose activation hook never ran, which on a multisite network is
     * every site but the one activation happened on.
     *
     * @param array<int, array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}> $steps
     */
    private function floor(array $steps): int
    {
        $stored = get_option(self::FLOOR_OPTION, '');

        if (is_numeric($stored) && (int) $stored > 0) {
            return (int) $stored;
        }

        $delays = [0];
        foreach ($steps as $step) {
            $delays[] = max(0, absint($step['delay'] ?? 0));
        }

        $floor = time() - (max($delays) * DAY_IN_SECONDS);

        // add_option, not update_option: a re-activation must never move the
        // floor forward over follow-ups that are still due.
        add_option(self::FLOOR_OPTION, (string) $floor, '', false);

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

        // Claims whose send never returned (a fatal or max_execution_time
        // mid-wp_mail). They are picked up first and retried once; without this
        // the claim written before the send would bury them for good.
        $orders = $this->query([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an equality match on an indexed meta key, bounded by the batch limit.
            'meta_query' => [
                [
                    'key'   => $metaKey,
                    'value' => self::CLAIM_PENDING,
                ],
            ],
            'limit' => self::BATCH_LIMIT,
        ]);

        $remaining = self::BATCH_LIMIT - count($orders);

        if ($remaining > 0) {
            $orders = array_merge($orders, $this->query([
                'status'        => $status,
                'orderby'       => 'modified',
                'order'         => 'ASC',
                'date_created'  => '>=' . $floor,
                'date_modified' => '<=' . $before,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by status + date + batch limit; the "not sent" flag must be queried.
                'meta_query'    => [
                    [
                        'key'     => $metaKey,
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                'limit' => $remaining,
            ]));
        }

        if ([] === $orders) {
            return 0;
        }

        $sent = 0;
        foreach ($orders as $order) {
            /**
             * Gate whether a follow-up should send on this cron run.
             *
             * @param bool     $send  Whether to send (default true).
             * @param \WC_Order $order The candidate order.
             * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step The sequence step.
             */
            if (! apply_filters('followup/should_send', true, $order, $step)) {
                continue;
            }

            $claim = (string) $order->get_meta($metaKey);

            // Anything other than an unfinished claim means this order is done
            // with: it was sent, or it was retried once and still did not
            // finish. Re-read rather than trust the query, to avoid a race with
            // a parallel run.
            if ('' !== $claim && self::CLAIM_PENDING !== $claim) {
                continue;
            }

            // Claim the order before sending, so a crash mid-send cannot mail
            // the same customer twice. The claim records which attempt this is,
            // so an attempt that never returns is retried exactly once and then
            // left alone; the send timestamp is written once wp_mail accepts it.
            $order->update_meta_data($metaKey, '' === $claim ? self::CLAIM_PENDING : self::CLAIM_GAVE_UP);
            $order->save_meta_data();

            if ($this->mailer->send($order, $step)) {
                $order->update_meta_data($metaKey, gmdate('c'));
                $order->save_meta_data();
                ++$sent;
                continue;
            }

            if ('' === $claim) {
                // wp_mail refused the message (no address, empty template).
                // Roll the claim back so a transient failure can retry tomorrow.
                $order->delete_meta_data($metaKey);
                $order->save_meta_data();
            }
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
