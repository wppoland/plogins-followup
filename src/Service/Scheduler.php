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
 */
final class Scheduler implements HasHooks
{
    public const CRON_HOOK = 'followup_daily_event';

    /** Per-order meta prefix recording when a follow-up was sent. */
    private const META_PREFIX = '_followup_sent_';

    /** Maximum orders processed per type per run, to keep cron bounded. */
    private const BATCH_LIMIT = 200;

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
     */
    public function run(): void
    {
        if (! function_exists('wc_get_orders')) {
            return;
        }

        foreach ($this->sequenceSteps->resolve() as $step) {
            if (empty($step['enabled'])) {
                continue;
            }

            $this->processStep($step);
        }
    }

    /**
     * Find and send one sequence step to all currently-due orders.
     *
     * @param array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string} $step
     */
    private function processStep(array $step): int
    {
        $type   = sanitize_key((string) ($step['id'] ?? ''));
        $status = sanitize_key((string) ($step['status'] ?? 'completed'));
        $delay  = max(0, absint($step['delay'] ?? 0));

        if ('' === $type) {
            return 0;
        }

        // Only orders modified on/before this cutoff are old enough. We use the
        // order's last-modified date as a proxy for "entered status N days ago";
        // it is conservative (never sends earlier than the delay).
        $before = gmdate('Y-m-d H:i:s', time() - ($delay * DAY_IN_SECONDS));

        $metaKey = self::META_PREFIX . sanitize_key($type);

        $orders = wc_get_orders([
            'status'        => $status,
            'type'          => 'shop_order',
            'limit'         => self::BATCH_LIMIT,
            'orderby'       => 'modified',
            'order'         => 'ASC',
            'date_modified' => '<=' . $before,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by status + date + batch limit; the "not sent" flag must be queried.
            'meta_query'    => [
                [
                    'key'     => $metaKey,
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'return'        => 'objects',
        ]);

        if (! is_array($orders) || [] === $orders) {
            return 0;
        }

        $sent = 0;
        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order) {
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
                continue;
            }

            // Claim the order first (idempotency guard): mark before sending so a
            // crash mid-send cannot cause a duplicate on the next run. Re-check
            // the flag to avoid a race with a parallel run.
            if ('' !== (string) $order->get_meta($metaKey)) {
                continue;
            }
            $order->update_meta_data($metaKey, gmdate('c'));
            $order->save_meta_data();

            $ok = $this->mailer->send($order, $step);

            if (! $ok) {
                // Roll back the claim so a transient failure can retry tomorrow.
                $order->delete_meta_data($metaKey);
                $order->save_meta_data();
                continue;
            }

            ++$sent;
        }

        return $sent;
    }
}
