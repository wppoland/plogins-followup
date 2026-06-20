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
 *  - {coupon}   replaced by Followup Pro when coupon blocks are enabled
 *
 * Sending is intentionally simple: plain-text messages via wp_mail, so they
 * inherit whatever mail configuration the site already uses.
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
     *
     * @param array{id: string, enabled?: bool, status?: string, delay?: int, subject: string, body: string} $step
     */
    public function send(\WC_Order $order, array $step): bool
    {
        $type = sanitize_key((string) ($step['id'] ?? ''));
        if ('' === $type) {
            return false;
        }

        $recipient = sanitize_email((string) $order->get_billing_email());
        if ('' === $recipient || ! is_email($recipient)) {
            return false;
        }

        $subject = $this->render((string) ($step['subject'] ?? ''), $order);
        $body    = $this->render((string) ($step['body'] ?? ''), $order);

        if ('' === trim($subject) || '' === trim($body)) {
            return false;
        }

        $mail = [
            'to'      => $recipient,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $this->headers(),
        ];

        /**
         * Filters the follow-up email arguments just before sending.
         *
         * Add-ons (e.g. Followup Pro) use this to transform the plain-text body
         * into branded HTML and adjust the Content-Type header. By default the
         * arguments are sent unchanged as a plain-text message via wp_mail.
         *
         * @param array{to: string, subject: string, body: string, headers: array<int, string>} $mail  Email arguments.
         * @param \WC_Order                                                                       $order The order being followed up.
         * @param string                                                                          $type  The follow-up type (e.g. "thankyou", "review").
         */
        $mail = (array) apply_filters('followup/mail', $mail, $order, $type);

        $sent = (bool) wp_mail(
            (string) ($mail['to'] ?? $recipient),
            (string) ($mail['subject'] ?? $subject),
            (string) ($mail['body'] ?? $body),
            (array) ($mail['headers'] ?? []),
        );

        if ($sent) {
            /**
             * Fires after a follow-up email is accepted by wp_mail.
             *
             * @param \WC_Order $order The order that received the follow-up.
             * @param array{id: string, enabled?: bool, status?: string, delay?: int, subject: string, body: string} $step  The sequence step.
             * @param array{to: string, subject: string, body: string, headers: array<int, string>} $mail Final mail arguments.
             */
            do_action('followup/email_sent', $order, $step, $mail);
        }

        return $sent;
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
