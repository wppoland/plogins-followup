<?php

declare(strict_types=1);

namespace Followup\Service;

use Followup\Settings;

defined('ABSPATH') || exit;

/**
 * Renders a follow-up template for an order and sends it via wp_mail.
 *
 * Placeholders supported in subject + body:
 *  - {customer} the customer's first name (falls back to "there")
 *  - {order}    the order number (e.g. #1234)
 *  - {site}     the site/blog name
 *
 * Sending is intentionally simple (wp_mail, plain text); deliverability tuning,
 * HTML templates and per-status scheduling live in the PRO add-on.
 */
final class Mailer
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Send a single follow-up email for an order. Returns true when wp_mail
     * accepted the message. Performs no idempotency checks itself — the caller
     * (Scheduler) owns "send once" semantics.
     */
    public function send(\WC_Order $order, string $type): bool
    {
        $config = $this->settings->email($type);
        if (null === $config) {
            return false;
        }

        $recipient = sanitize_email((string) $order->get_billing_email());
        if ('' === $recipient || ! is_email($recipient)) {
            return false;
        }

        $subject = $this->render((string) ($config['subject'] ?? ''), $order);
        $body    = $this->render((string) ($config['body'] ?? ''), $order);

        if ('' === trim($subject) || '' === trim($body)) {
            return false;
        }

        $headers = $this->headers();

        /**
         * Filter the rendered follow-up email just before sending.
         *
         * @param array{to: string, subject: string, body: string, headers: array<int, string>} $mail
         * @param \WC_Order $order
         * @param string    $type
         */
        $mail = apply_filters('followup/mail', [
            'to'      => $recipient,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
        ], $order, $type);

        return (bool) wp_mail(
            (string) $mail['to'],
            (string) $mail['subject'],
            (string) $mail['body'],
            (array) $mail['headers'],
        );
    }

    /**
     * Replace the supported placeholders in a template string.
     */
    public function render(string $template, \WC_Order $order): string
    {
        $first = trim((string) $order->get_billing_first_name());
        if ('' === $first) {
            $first = __('there', 'followup');
        }

        $replacements = [
            '{customer}' => $first,
            '{order}'    => '#' . $order->get_order_number(),
            '{site}'     => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        ];

        return strtr($template, $replacements);
    }

    /**
     * Build the From header from the configured name/email, falling back to the
     * WooCommerce store sender so emails always have a sane origin.
     *
     * @return array<int, string>
     */
    private function headers(): array
    {
        $all   = $this->settings->all();
        $name  = trim((string) ($all['from_name'] ?? ''));
        $email = sanitize_email((string) ($all['from_email'] ?? ''));

        if ('' === $name) {
            $name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        }
        if ('' === $email || ! is_email($email)) {
            $email = (string) get_option('admin_email');
        }

        if ('' === $email || ! is_email($email)) {
            return [];
        }

        return [ sprintf('From: %s <%s>', $name, $email) ];
    }
}
