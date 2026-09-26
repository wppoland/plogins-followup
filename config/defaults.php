<?php
/**
 * Default settings, merged under the option key `followup_settings`.
 *
 * Each follow-up type has: enabled (bool), the order status that triggers it,
 * the delay in days after that status, and subject + body templates with
 * {customer} / {order} / {site} placeholders.
 *
 * @package Followup
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'from_name'  => '',
    'from_email' => '',

    // Every subject and body below is empty on purpose. A sentence written here
    // is not a gettext call, so it never reaches the .pot and no translator can
    // reach it; once the settings screen had stored it, the shop mailed English
    // whatever language pack was installed. Empty means "use
    // Followup\Service\Texts", which is translated; anything a merchant writes
    // still wins.
    'emails'     => [
        'thank_you' => [
            'enabled' => true,
            'status'  => 'completed',
            'delay'   => 1,
            'subject' => '',
            'body'    => '',
        ],
        'review' => [
            'enabled' => true,
            'status'  => 'completed',
            'delay'   => 7,
            'subject' => '',
            'body'    => '',
        ],
    ],
];
