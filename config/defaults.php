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
    'emails'     => [
        'thank_you' => [
            'enabled' => true,
            'status'  => 'completed',
            'delay'   => 1,
            'subject' => 'Thanks for your order, {customer}!',
            'body'    => "Hi {customer},\n\nThank you for shopping with {site}. We hope you love order {order}.\n\nIf you need anything at all, just reply to this email.\n\nWarm regards,\n{site}",
        ],
        'review' => [
            'enabled' => true,
            'status'  => 'completed',
            'delay'   => 7,
            'subject' => 'How did we do, {customer}?',
            'body'    => "Hi {customer},\n\nYou received order {order} a little while ago. Would you take a moment to leave a review? It helps us a lot and helps other shoppers too.\n\nThank you,\n{site}",
        ],
    ],
];
