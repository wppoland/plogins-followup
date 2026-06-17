<?php

declare(strict_types=1);

namespace Followup\Service;

use Followup\FollowupTypes;
use Followup\Settings;

defined('ABSPATH') || exit;

/**
 * Resolves the ordered list of follow-up steps the daily cron should process.
 *
 * The default list is built from the packaged follow-up types and their saved
 * settings. Add-ons filter `followup/sequence_steps` to replace or extend it.
 */
final class SequenceSteps
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @return array<int, array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}>
     */
    public function resolve(): array
    {
        $steps = [];

        foreach (FollowupTypes::all() as $type => $_meta) {
            $config = $this->settings->email($type);
            if (null === $config) {
                continue;
            }

            $steps[] = $this->normalizeStep([
                'id'      => $type,
                'enabled' => ! empty($config['enabled']),
                'status'  => (string) ($config['status'] ?? 'completed'),
                'delay'   => max(0, absint($config['delay'] ?? 0)),
                'subject' => (string) ($config['subject'] ?? ''),
                'body'    => (string) ($config['body'] ?? ''),
            ]);
        }

        /**
         * Filters the follow-up sequence processed by the daily cron worker.
         *
         * Each step must use:
         *  - id       string  Unique step id (used for per-order sent meta).
         *  - enabled  bool    Whether the step is active.
         *  - status   string  WooCommerce order status slug (without `wc-`).
         *  - delay    int     Days after entering the status before sending.
         *  - subject  string  Email subject template.
         *  - body     string  Email body template.
         *
         * @param array<int, array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}> $steps Resolved steps.
         */
        $filtered = apply_filters('followup/sequence_steps', $steps);

        return $this->sanitizeList(is_array($filtered) ? $filtered : $steps);
    }

    /**
     * @param array<string, mixed> $step
     * @return array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}
     */
    private function normalizeStep(array $step): array
    {
        $id = sanitize_key((string) ($step['id'] ?? ''));

        return [
            'id'      => '' !== $id ? $id : 'step',
            'enabled' => ! empty($step['enabled']),
            'status'  => sanitize_key((string) ($step['status'] ?? 'completed')),
            'delay'   => max(0, absint($step['delay'] ?? 0)),
            'subject' => sanitize_text_field((string) ($step['subject'] ?? '')),
            'body'    => sanitize_textarea_field((string) ($step['body'] ?? '')),
        ];
    }

    /**
     * @param array<int, mixed> $steps
     * @return array<int, array{id: string, enabled: bool, status: string, delay: int, subject: string, body: string}>
     */
    private function sanitizeList(array $steps): array
    {
        $out   = [];
        $seen  = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $normalized = $this->normalizeStep($step);
            if ('' === $normalized['id'] || isset($seen[ $normalized['id'] ])) {
                continue;
            }

            if ('' === trim($normalized['subject']) && '' === trim($normalized['body'])) {
                continue;
            }

            $seen[ $normalized['id'] ] = true;
            $out[]                     = $normalized;
        }

        return $out;
    }
}
