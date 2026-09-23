<?php
/**
 * Plugin Name:       Sekvo - Follow-Up Emails for WooCommerce
 * Plugin URI:        https://plogins.com/plogins-followup/
 * Description:        Send automated post-purchase emails to WooCommerce customers: thank-you and review requests, a set number of days after an order.
 * Version:           1.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sekvo
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.0
 *
 * @package Followup
 */

declare(strict_types=1);

namespace Followup;

defined('ABSPATH') || exit;

const VERSION     = '1.1.1';
const PLUGIN_FILE = __FILE__;

define('FOLLOWUP_DIR', plugin_dir_path(__FILE__));
define('FOLLOWUP_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// Schedule (and tear down) the daily follow-up cron. Activation hooks must work
// without the full container, so we touch the schedule directly here.
register_activation_hook(__FILE__, static function (): void {
    // Record the install moment as the floor for the sender. Without it the
    // first run would treat the shop's entire order history as due and start
    // mailing customers who ordered years ago.
    //
    // add_option tells us which activation this is. When it reports the floor
    // was already there, the plugin has been switched on before and switched
    // off since, and nothing went out while it was off. The reach-back has to
    // move forward or the first run mails everything that piled up, but not
    // past the orders still inside their window, and that window is per step.
    // So the moment is recorded here and every run after it measures each
    // step's own delay back from it, where the configured delays (including any
    // a PRO step adds) are known.
    if (! add_option(Service\Scheduler::FLOOR_OPTION, (string) time(), '', false)) {
        update_option(Service\Scheduler::REACTIVATED_OPTION, (string) time(), false);
    }

    if (! wp_next_scheduled(Service\Scheduler::CRON_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', Service\Scheduler::CRON_HOOK);
    }
});

register_deactivation_hook(__FILE__, static function (): void {
    $timestamp = wp_next_scheduled(Service\Scheduler::CRON_HOOK);
    if (false !== $timestamp) {
        wp_unschedule_event($timestamp, Service\Scheduler::CRON_HOOK);
    }
    wp_clear_scheduled_hook(Service\Scheduler::CRON_HOOK);
});

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Sekvo requires WooCommerce to be installed and activated.', 'sekvo');
            echo '</p></div>';
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);
