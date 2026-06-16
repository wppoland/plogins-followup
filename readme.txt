=== Followup - Order Follow-Up Emails for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send automated post-purchase emails for WooCommerce: thank-you and review requests, a set number of days after an order.

== Description ==

Followup sends automated post-purchase emails to your WooCommerce customers, a configurable number of days after an order reaches a status such as Completed.

Two email types come ready to use:

* **Thank-you**: a short note shortly after the order is fulfilled.
* **Review request**: asks for a review once the customer has had the product for a while.

For each type you set whether it is enabled, which order status triggers it, how many days to wait, and the subject and body. Subjects and bodies support `{customer}` (first name), `{order}` (order number) and `{site}` (site name).

A daily wp-cron event picks up orders that are due and sends the emails with `wp_mail`, so they use whatever mail setup the site already has. Each follow-up is recorded against the order as soon as it sends, so the same one is never sent twice, even if two cron runs overlap.

The plugin is not on the WordPress.org directory yet. Source code and issue tracker live at https://github.com/wppoland/followup.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/followup`, or install via Plugins -> Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce -> Follow-ups to enable email types and edit the templates.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= When are emails actually sent? =

A daily wp-cron event checks for orders that have been in the configured status for at least the configured number of days, and sends any that have not been sent yet.

= Will a customer ever get the same email twice? =

No. Each follow-up type is recorded against the order once it is sent, so it is never sent again for that order.

= Which placeholders can I use? =

`{customer}` (first name), `{order}` (order number) and `{site}` (your site name), in both the subject and body.

= Which order statuses can trigger a follow-up? =

You choose the trigger status per email type (for example processing or completed) and the delay in days before it sends.

== Screenshots ==

1. The Follow-ups settings screen: enable each email type and edit its trigger status, delay and templates.

== Changelog ==

= 0.1.0 =
* Initial release: thank-you and review request follow-up emails with per-type enable, trigger status, delay and templates; idempotent daily sender.
