<?php

declare(strict_types=1);

namespace Followup\Admin;

use Followup\Contract\HasHooks;
use Followup\FollowupTypes;
use Followup\Service\Scheduler;
use Followup\Service\Texts;
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

    private ?ProUpsell $proUpsell = null;

    public function __construct(private readonly Settings $settings)
    {
    }

    private function proUpsell(): ProUpsell
    {
        return $this->proUpsell ??= new ProUpsell();
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        $this->proUpsell()->registerHooks();
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Follow-up Emails', 'plogins-followup'),
            __('Follow-ups', 'plogins-followup'),
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

        wp_enqueue_script(
            'followup-admin',
            FOLLOWUP_URL . 'assets/js/admin.js',
            [],
            \Followup\VERSION,
            true,
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        // Raw, never Texts::apply(): the fields edit what is stored. Rendering the
        // resolved default into the input would save that one language back into
        // the option on the next save, which is the bug this avoids. The
        // translated default is shown as a placeholder instead.
        $settings = $this->settings->raw();
        $texts    = Texts::defaults();
        $option   = Settings::OPTION;
        $types    = FollowupTypes::all();
        $statuses = $this->orderStatuses();
        $anyOn    = $this->anyEnabled($settings, $types);

        // Concrete fall-backs so "leave blank" shows what it actually resolves to.
        $defaultName  = (string) get_bloginfo('name');
        $defaultEmail = (string) get_option('admin_email', '');

        // The cut-off the sender will not reach back past. Absent until the
        // plugin is activated or the first daily run seeds it.
        $floor      = get_option(Scheduler::FLOOR_OPTION, '');
        $dateFormat = (string) get_option('date_format', 'Y-m-d');
        $floorLabel = is_numeric($floor)
            ? wp_date($dateFormat, (int) $floor)
            : '';

        // The last time the plugin was switched back on, read exactly as the
        // sender reads it. While one is recorded the cut-off above is not the
        // whole story: each step also measures its own delay back from this
        // moment, so there is a stretch after the cut-off that is never mailed.
        $resumed      = get_option(Scheduler::REACTIVATED_OPTION, '');
        $resumedLabel = is_numeric($resumed)
            ? wp_date($dateFormat, (int) $resumed)
            : '';
        ?>
        <div class="wrap followup-admin">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <?php if ($anyOn) : ?>
                    <span class="followup-admin__status followup-admin__status--on"><?php esc_html_e('Active', 'plogins-followup'); ?></span>
                <?php else : ?>
                    <span class="followup-admin__status followup-admin__status--off"><?php esc_html_e('No emails enabled', 'plogins-followup'); ?></span>
                <?php endif; ?>
            </h1>

            <?php $this->proUpsell()->banner(); ?>

            <div class="followup-admin__intro">
                <span class="followup-admin__intro-icon" aria-hidden="true">&#9993;</span>
                <div>
                    <h2><?php esc_html_e('Automated post-purchase emails', 'plogins-followup'); ?></h2>
                    <p><?php esc_html_e('Each enabled email is sent once per order, a set number of days after the order reaches the chosen status. A daily background task finds due orders and sends them. The order is marked before the email is handed over, so a run that dies halfway through cannot quietly send it again every day.', 'plogins-followup'); ?></p>
                    <p>
                        <?php
                        if ('' !== $floorLabel) {
                            printf(
                                /* translators: %1$s: date of the cut-off before which orders are never followed up. %2$s: maximum emails per type per run. */
                                esc_html__('No order placed before %1$s is ever followed up, so the customers who ordered before then were not mailed. That date is as far back as the sender reaches, not a promise about every order after it. Each daily run sends at most %2$s emails per type, oldest orders first.', 'plogins-followup'),
                                '<strong>' . esc_html($floorLabel) . '</strong>',
                                '<strong>' . esc_html(number_format_i18n(Scheduler::BATCH_LIMIT)) . '</strong>'
                            );
                        } else {
                            printf(
                                /* translators: %s: maximum emails per type per run. */
                                esc_html__('No order placed before this plugin started running here is ever followed up, so the customers who ordered before then were not mailed. That moment is as far back as the sender reaches, not a promise about every order after it. Each daily run sends at most %s emails per type, oldest orders first.', 'plogins-followup'),
                                '<strong>' . esc_html(number_format_i18n(Scheduler::BATCH_LIMIT)) . '</strong>'
                            );
                        }

                        if ('' !== $resumedLabel) {
                            echo ' ';
                            printf(
                                /* translators: %s: date the plugin was last switched back on. */
                                esc_html__('The plugin was last switched back on %s, and from that moment each email reaches back by its own delay and no further, so orders that passed their delay while it was off are left alone as well.', 'plogins-followup'),
                                '<strong>' . esc_html($resumedLabel) . '</strong>'
                            );
                        }
                        ?>
                    </p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="followup-admin__card">
                    <h2><?php esc_html_e('Sender', 'plogins-followup'); ?></h2>
                    <p class="followup-admin__card-hint"><?php esc_html_e('Who follow-up emails appear to come from. Leave blank to use your store name and admin email.', 'plogins-followup'); ?></p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="followup_from_name"><?php esc_html_e('From name', 'plogins-followup'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="followup_from_name" class="regular-text"
                                        name="<?php echo esc_attr($option); ?>[from_name]"
                                        placeholder="<?php echo esc_attr($defaultName); ?>"
                                        value="<?php echo esc_attr((string) ($settings['from_name'] ?? '')); ?>" />
                                    <p class="description">
                                        <?php
                                        /* translators: %s: the store name used as the fall-back sender. */
                                        printf(esc_html__('Shown as the sender in the customer inbox. Left blank, emails come from %s.', 'plogins-followup'), '<strong>' . esc_html($defaultName) . '</strong>');
                                        ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="followup_from_email"><?php esc_html_e('From email', 'plogins-followup'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="followup_from_email" class="regular-text"
                                        name="<?php echo esc_attr($option); ?>[from_email]"
                                        placeholder="<?php echo esc_attr($defaultEmail); ?>"
                                        value="<?php echo esc_attr((string) ($settings['from_email'] ?? '')); ?>" />
                                    <p class="description">
                                        <?php
                                        /* translators: %s: the admin email used as the fall-back sender address. */
                                        printf(esc_html__('Reply-to address for these emails. Left blank, they send from %s.', 'plogins-followup'), '<strong>' . esc_html($defaultEmail) . '</strong>');
                                        ?>
                                        <?php esc_html_e('An address on your own domain delivers most reliably.', 'plogins-followup'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="followup-tokens" role="group" aria-label="<?php esc_attr_e('Available template tokens', 'plogins-followup'); ?>">
                    <span class="followup-tokens__label"><?php esc_html_e('Tokens you can use in subjects and bodies:', 'plogins-followup'); ?></span>
                    <code>{customer}</code> <code>{order}</code> <code>{site}</code>
                </div>

                <?php foreach ($types as $type => $meta) :
                    $email   = isset($settings['emails'][ $type ]) && is_array($settings['emails'][ $type ]) ? $settings['emails'][ $type ] : [];
                    $enabled = ! empty($email['enabled']);
                    $base    = $option . '[emails][' . $type . ']';
                    $id      = 'followup_' . $type;
                    ?>
                    <?php
                    $curStatus = (string) ($email['status'] ?? 'completed');
                    $curDelay  = (int) absint($email['delay'] ?? 0);
                    $sendLabel = (string) ($statuses[ $curStatus ] ?? ucfirst($curStatus));
                    ?>
                    <div class="followup-admin__card followup-email <?php echo $enabled ? 'is-enabled' : ''; ?>">
                        <div class="followup-email__head">
                            <h2><?php echo esc_html((string) $meta['label']); ?></h2>
                            <label class="followup-switch" for="<?php echo esc_attr($id . '_enabled'); ?>"
                                title="<?php esc_attr_e('When off, this email is never scheduled or sent.', 'plogins-followup'); ?>">
                                <input type="checkbox" id="<?php echo esc_attr($id . '_enabled'); ?>"
                                    class="followup-email__toggle"
                                    name="<?php echo esc_attr($base); ?>[enabled]" value="1" <?php checked($enabled, true); ?> />
                                <span class="followup-switch__text"><?php esc_html_e('Send this email', 'plogins-followup'); ?></span>
                            </label>
                        </div>
                        <p class="followup-admin__card-hint"><?php echo esc_html((string) $meta['description']); ?></p>

                        <?php
                        /* translators: %s: order status name, e.g. Completed. */
                        $dropLine = sprintf(__('Order reaches %s', 'plogins-followup'), $sendLabel);
                        /* translators: %d: number of days. */
                        $waitLine = sprintf(_n('wait %d day', 'wait %d days', $curDelay, 'plogins-followup'), $curDelay);
                        if (0 === $curDelay) {
                            $waitLine = __('next daily run', 'plogins-followup');
                        }
                        ?>
                        <div class="followup-postmark" aria-hidden="true"
                            data-fu-units="<?php echo esc_attr(__('day', 'plogins-followup') . '|' . __('days', 'plogins-followup')); ?>"
                            data-fu-soon="<?php echo esc_attr__('soon', 'plogins-followup'); ?>">
                            <span class="followup-postmark__drop"><?php echo esc_html($dropLine); ?></span>
                            <span class="followup-postmark__line"></span>
                            <span class="followup-postmark__stamp" data-fu-stamp>
                                <span class="followup-postmark__days"><?php echo esc_html((string) $curDelay); ?></span>
                                <span class="followup-postmark__days-unit"><?php echo esc_html(0 === $curDelay ? __('soon', 'plogins-followup') : _n('day', 'days', $curDelay, 'plogins-followup')); ?></span>
                            </span>
                            <span class="followup-postmark__line"></span>
                            <span class="followup-postmark__send"><?php esc_html_e('Email sent', 'plogins-followup'); ?></span>
                        </div>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_status'); ?>"><?php esc_html_e('Trigger status', 'plogins-followup'); ?></label>
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
                                        <p class="description"><?php esc_html_e('The email is scheduled once an order reaches this status.', 'plogins-followup'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_delay'); ?>"><?php esc_html_e('Delay (days)', 'plogins-followup'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" min="0" max="3650" step="1"
                                            id="<?php echo esc_attr($id . '_delay'); ?>"
                                            name="<?php echo esc_attr($base); ?>[delay]"
                                            value="<?php echo esc_attr((string) absint($email['delay'] ?? 0)); ?>"
                                            data-fu-delay
                                            class="small-text" />
                                        <span class="description"><?php esc_html_e('days after the order reaches the status above', 'plogins-followup'); ?></span>
                                        <p class="description"><?php esc_html_e('Use 0 to send on the next daily run.', 'plogins-followup'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_subject'); ?>"><?php esc_html_e('Subject', 'plogins-followup'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="<?php echo esc_attr($id . '_subject'); ?>" class="large-text"
                                            name="<?php echo esc_attr($base); ?>[subject]"
                                            placeholder="<?php echo esc_attr((string) ($texts[ 'emails.' . $type . '.subject' ] ?? '')); ?>"
                                            value="<?php echo esc_attr((string) ($email['subject'] ?? '')); ?>" />
                                        <p class="description"><?php esc_html_e('The inbox subject line. Tokens above work here too, a name in the subject lifts open rates.', 'plogins-followup'); ?></p>
                                        <p class="description"><?php esc_html_e('Leave blank to use the wording shown in grey, which is translated into your store language.', 'plogins-followup'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($id . '_body'); ?>"><?php esc_html_e('Body', 'plogins-followup'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="<?php echo esc_attr($id . '_body'); ?>" rows="6" class="large-text"
                                            name="<?php echo esc_attr($base); ?>[body]"
                                            placeholder="<?php echo esc_attr((string) ($texts[ 'emails.' . $type . '.body' ] ?? '')); ?>"><?php echo esc_textarea((string) ($email['body'] ?? '')); ?></textarea>
                                        <p class="description"><?php esc_html_e('Plain text. Tokens above are replaced when the email is sent.', 'plogins-followup'); ?></p>
                                        <p class="description"><?php esc_html_e('Leave blank to use the wording shown in grey, which is translated into your store language.', 'plogins-followup'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>

            <?php $this->proUpsell()->cards(); ?>
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

            // A blank field is stored blank. Substituting the packaged English
            // here is what used to freeze one language into the option; blank
            // means "use the translated default" and is resolved on send.
            $subject = isset($in['subject']) ? sanitize_text_field((string) $in['subject']) : '';
            $body    = isset($in['body']) ? sanitize_textarea_field((string) $in['body']) : '';

            $out['emails'][ $type ] = [
                'enabled' => ! empty($in['enabled']),
                'status'  => $status,
                'delay'   => min(3650, absint($in['delay'] ?? $defaultEmail['delay'])),
                'subject' => $subject,
                'body'    => $body,
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
            $out = ['completed' => __('Completed', 'plogins-followup')];
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
