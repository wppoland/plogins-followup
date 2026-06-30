<?php

declare(strict_types=1);

namespace Followup;

defined('ABSPATH') || exit;

/**
 * Registry of the follow-up email types the plugin ships. Keeps labels and
 * ordering in one place so the admin UI and the sender stay in sync.
 */
final class FollowupTypes
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'thank_you' => [
                'label'       => __('Thank-you', 'plogins-followup'),
                'description' => __('A warm thank-you sent shortly after the order is fulfilled.', 'plogins-followup'),
            ],
            'review' => [
                'label'       => __('Review request', 'plogins-followup'),
                'description' => __('Asks the customer to leave a review once they have had time with the product.', 'plogins-followup'),
            ],
        ];
    }

    /**
     * Whether a given type key is registered.
     */
    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::all());
    }
}
