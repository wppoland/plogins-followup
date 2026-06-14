<?php

declare(strict_types=1);

namespace Followup;

defined('ABSPATH') || exit;

/**
 * Registry of the follow-up email types the FREE plugin ships. Keeps labels and
 * ordering in one place so the admin UI and the sender stay in sync. The list
 * is filterable so the PRO add-on (or site code) can register more types.
 */
final class FollowupTypes
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        $types = [
            'thank_you' => [
                'label'       => __('Thank-you', 'followup'),
                'description' => __('A warm thank-you sent shortly after the order is fulfilled.', 'followup'),
            ],
            'review' => [
                'label'       => __('Review request', 'followup'),
                'description' => __('Asks the customer to leave a review once they have had time with the product.', 'followup'),
            ],
            'cross_sell' => [
                'label'       => __('Cross-sell', 'followup'),
                'description' => __('Suggests related products a couple of weeks after purchase.', 'followup'),
            ],
            'win_back' => [
                'label'       => __('Win-back', 'followup'),
                'description' => __('Re-engages a customer who has not ordered again after a longer gap.', 'followup'),
            ],
        ];

        /**
         * Filter the registered follow-up email types.
         *
         * @param array<string, array{label: string, description: string}> $types
         */
        $filtered = apply_filters('followup/types', $types);

        return is_array($filtered) ? $filtered : $types;
    }

    /**
     * Whether a given type key is registered.
     */
    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::all());
    }
}
