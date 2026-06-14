<?php

declare(strict_types=1);

namespace Followup\Admin;

use Followup\Contract\HasHooks;
use Followup\FollowupTypes;
use Followup\Settings;

defined('ABSPATH') || exit;

/**
 * WooCommerce submenu settings page ("Follow-ups").
 *
 * Per follow-up type: enable, trigger order status, delay in days, and subject +
 * body templates. Plus global From name/email. All output is escaped; all input
 * is sanitised on save; the save capability is aligned to manage_woocommerce so
 * shop managers can save.
 */
final class SettingsPage implements HasHooks
{
    private const PAGE = 'followup-settings';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Follow-up Emails', 'followup'),
            __('Follow-ups', 'followup'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            Settings::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (! str_contains($hook, self::PAGE)) {
            return;
        }

        wp_enqueue_style(
            'followup-admin',
            FOLLOWUP_URL . 'assets/css/admin.css',
            [],
            \Followup\VERSION,
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings->all();
        $option   = Settings::OPTION;
        $types    = FollowupTypes::all();
        $statuses = $this->orderStatuses();
        $anyOn    = $this->anyEnabled($settings, $types);
        ?>
        <div class="wrap followup-admin">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <?php if ($anyOn) : ?>
                    <span class="followup-admin__status followup-admin__status--on"><?php esc_html_e('Active', 'followup'); ?></span>
                <?php else : ?>
                    <span class="followup-admin__status followup-admin__status--off"><?php esc_html_e('No emails enabled', 'followup'); ?></span>
                <?php endif; ?>
            </h1>

            <div class="followup-admin__intro">
                <span class="followup-admin__intro-icon" aria-hidden="true">&#9993;</span>
                <div>
                    <h2><?php esc_html_e('Automated post-purchase emails', 'followup'); ?></h2>
                    <p><?php esc_html_e('Each enabled email is sent once per order, a set number of days after the order reaches the chosen status. A daily background task finds due orders and sends them. The same email is never sent twice for the same order.', 'followup'); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="followup-admin__card">
                    <h2><?php esc_html_e('Sender', 'followup'); ?></h2>
                    <p class="followup-admin__card-hint"><?php esc_html_e('Who follow-up emails appear to come from. Leave blank to use your store name and admin email.', 'followup'); ?></p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="followup_from_name"><?php esc_html_e('From name', 'followup'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="followup_from_name" class="regular-text"
                                        name="<?php echo esc_attr($option); ?>[from_name]"
                                        value="<?php echo esc_attr((string) ($settings['from_name'] ?? '')); ?>" />
                                    <p class="description"><?php esc_html_e('The sender name shown in the customer\'s inbox. Defaults to your site name.', 'followup'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="followup_from_email"><?php esc_html_e('From email', 'followup'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="followup_from_email" class="regular-text"
                                        name="<?php echo esc_attr($option); ?>[from_email]"
                                        value="<?php echo esc_attr((string) ($settings['from_email'] ?? '')); ?>" />
                                    <p class="description"><?php esc_html_e('The sender address. Defaults to your site admin email. Use an address on your own domain for the best deliverability.', 'followup'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="followup-tokens" role="group" aria-label="<?php esc_attr_e('Available template tokens', 'followup'); ?>">
                    <span class="followup-tokens__label"><?php esc_html_e('Tokens you can use in subjects and bodies:', 'followup'); ?></span>
                    <code>{customer}</code> <code>{order}</code> <code>{site}</code>
                </div>

                <?php foreach ($types as $type => $meta) :
                    $email   = isset($settings['emails'][ $type ]) && is_array($settings['emails'][ $type ]) ? $settings['emails'][ $type ] : [];
                    $enabled = ! empty($email['enabled']);
                    $base    = $option . '[emails][' . $type . ']';
                    $id      = 'followup_' . $type;
                    ?>
                    <div class="followup-admin__card followup-email <?php echo $enabled ? 'is-enabled' : ''; ?>">
                        <div class="followup-email__head">
                            <h2><?php echo esc_html((string) $meta['label']); ?></h2>
                            <label class="followup-switch" for="<?php echo esc_attr($id . '_enabled'); ?>">
                                <input type="checkbox" id="<?php echo esc_attr($id . '_enabled'); ?>"
                                    class="followup-email__toggle"
                                    name="<?php echo esc_attr($base); ?>[enabled]" value="1" <?php checked($enabled, true); ?> />
                                <span class="followup-switch__text"><?php esc_html_e('Enabled', 'followup'); ?></span>
                            </label>
                        </div>
                        <p class="followup-admin__card-hint"><?php echo esc_html((string) $meta['description']); ?></p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_status'); ?>"><?php esc_html_e('Trigger status', 'followup'); ?></label>
                                    </th>
                                    <td>
                                        <select id="<?php echo esc_attr($id . '_status'); ?>" name="<?php echo esc_attr($base); ?>[status]">
                                            <?php
                                            $current = (string) ($email['status'] ?? 'completed');
                                            foreach ($statuses as $slug => $label) :
                                                ?>
                                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($current, $slug); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e('The email is scheduled once an order reaches this status.', 'followup'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_delay'); ?>"><?php esc_html_e('Delay (days)', 'followup'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" min="0" max="3650" step="1"
                                            id="<?php echo esc_attr($id . '_delay'); ?>"
                                            name="<?php echo esc_attr($base); ?>[delay]"
                                            value="<?php echo esc_attr((string) absint($email['delay'] ?? 0)); ?>"
                                            class="small-text" />
                                        <span class="description"><?php esc_html_e('days after the order reaches the status above', 'followup'); ?></span>
                                        <p class="description"><?php esc_html_e('Use 0 to send on the next daily run.', 'followup'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_subject'); ?>"><?php esc_html_e('Subject', 'followup'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="<?php echo esc_attr($id . '_subject'); ?>" class="large-text"
                                            name="<?php echo esc_attr($base); ?>[subject]"
                                            value="<?php echo esc_attr((string) ($email['subject'] ?? '')); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_body'); ?>"><?php esc_html_e('Body', 'followup'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="<?php echo esc_attr($id . '_body'); ?>" rows="6" class="large-text"
                                            name="<?php echo esc_attr($base); ?>[body]"><?php echo esc_textarea((string) ($email['body'] ?? '')); ?></textarea>
                                        <p class="description"><?php esc_html_e('Plain text. Tokens above are replaced when the email is sent.', 'followup'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitise the submitted settings, preserving the shape of defaults and
     * dropping anything not on the form. Unknown email types are ignored.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $defaults = Settings::defaults();
        $statuses = $this->orderStatuses();

        $out = [
            'from_name'  => isset($raw['from_name']) ? sanitize_text_field((string) $raw['from_name']) : '',
            'from_email' => isset($raw['from_email']) ? sanitize_email((string) $raw['from_email']) : '',
            'emails'     => [],
        ];

        if ('' !== $out['from_email'] && ! is_email($out['from_email'])) {
            $out['from_email'] = '';
        }

        $rawEmails = isset($raw['emails']) && is_array($raw['emails']) ? $raw['emails'] : [];

        foreach ($defaults['emails'] as $type => $defaultEmail) {
            $in = isset($rawEmails[ $type ]) && is_array($rawEmails[ $type ]) ? $rawEmails[ $type ] : [];

            $status = isset($in['status']) ? sanitize_key((string) $in['status']) : (string) $defaultEmail['status'];
            if (! isset($statuses[ $status ])) {
                $status = (string) $defaultEmail['status'];
            }

            $subject = isset($in['subject']) ? sanitize_text_field((string) $in['subject']) : '';
            $body    = isset($in['body']) ? sanitize_textarea_field((string) $in['body']) : '';

            $out['emails'][ $type ] = [
                'enabled' => ! empty($in['enabled']),
                'status'  => $status,
                'delay'   => min(3650, absint($in['delay'] ?? $defaultEmail['delay'])),
                'subject' => '' !== $subject ? $subject : (string) $defaultEmail['subject'],
                'body'    => '' !== $body ? $body : (string) $defaultEmail['body'],
            ];
        }

        return $out;
    }

    /**
     * Available order statuses as slug => label (without the "wc-" prefix).
     *
     * @return array<string, string>
     */
    private function orderStatuses(): array
    {
        $out = [];
        if (function_exists('wc_get_order_statuses')) {
            foreach (wc_get_order_statuses() as $slug => $label) {
                $out[ (string) preg_replace('/^wc-/', '', (string) $slug) ] = (string) $label;
            }
        }

        if ([] === $out) {
            $out = ['completed' => __('Completed', 'followup')];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $types
     */
    private function anyEnabled(array $settings, array $types): bool
    {
        foreach (array_keys($types) as $type) {
            if (! empty($settings['emails'][ $type ]['enabled'])) {
                return true;
            }
        }

        return false;
    }
}
