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

Send automated post-purchase emails for WooCommerce: thank-you, review requests, cross-sell and win-back, a set number of days after an order.

== Description ==

Followup sends automated post-purchase emails to your WooCommerce customers, a configurable number of days after an order reaches a status such as Completed.

Four ready-to-use email types are included:

* **Thank-you** - a warm message shortly after the order is fulfilled.
* **Review request** - asks for a review once the customer has had time with the product.
* **Cross-sell** - suggests related products a couple of weeks later.
* **Win-back** - re-engages customers who have not ordered again after a longer gap.

For each type you control whether it is enabled, which order status triggers it, the delay in days, and the subject and body. Templates support `{customer}`, `{order}` and `{site}` placeholders.

A daily background task (wp-cron) finds orders that are due and sends the emails via `wp_mail`. Sending is idempotent: the same follow-up is never sent twice for the same order, tracked per-order so overlapping runs are safe.

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

== Screenshots ==

1. The Follow-ups settings screen: enable each email type and edit its trigger status, delay and templates.

== Changelog ==

= 0.1.0 =
* Initial release: thank-you, review request, cross-sell and win-back follow-up emails with per-type enable, trigger status, delay and templates; idempotent daily sender.
