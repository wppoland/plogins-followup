=== Plogins Followup - Follow-Up Emails for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.11
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

Developers can extend the sequence through the `followup/sequence_steps` filter. Each custom step can provide its own trigger status, delay, subject and body while reusing Followup's idempotent scheduler.

The plugin is not on the WordPress.org directory yet. Source code and issue tracker live at [github.com/wppoland/plogins-followup](https://github.com/wppoland/plogins-followup).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/followup`, or install via Plugins -> Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce -> Follow-ups to enable email types and edit the templates.

== Frequently Asked Questions ==

= Documentation and links =

* **Documentation**: [plogins.com/plogins-followup/docs/](https://plogins.com/plogins-followup/docs/)
* **Plugin page**: [plogins.com/plogins-followup/](https://plogins.com/plogins-followup/)
* **Source code**: [github.com/wppoland/plogins-followup](https://github.com/wppoland/plogins-followup)
* **Bug reports and feature requests**: [github.com/wppoland/plogins-followup/issues](https://github.com/wppoland/plogins-followup/issues)


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


= Does this plugin work on WordPress Multisite? =

Yes. This plugin is compatible with WordPress Multisite. Network activate it or activate it on individual sites; each site keeps its own settings and data.

== Screenshots ==

1. The Follow-ups settings screen: enable each email type and edit its trigger status, delay and templates.

== External Services ==

Followup does not connect to any external services. It has no API keys, sends no data off-site, and loads nothing from a remote URL or CDN. Everything runs on your own WordPress install: settings are stored in the `followup_settings` and `followup_db_version` options, and each sent follow-up is recorded as `_followup_sent_{type}` order meta so it is never sent twice. Emails go out through your site's own `wp_mail()` using your WooCommerce store sender, so they travel by whatever mail setup you already have.

== Translations ==

Plogins Followup is fully translatable and ships the `plogins-followup.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

= 1.0.11 =
* Fixed: the PRO upgrade promo kept selling to people who had already bought the paid edition. Only the banner could be dismissed, so the sidebar promo and the locked feature cards followed a paying customer around for good. The promo now checks whether the paid edition is active and steps aside when it is.
* Fixed: deleting the plugin left the per-user "dismiss" flag from the PRO notice in the database. Uninstall now removes it for every user, not just the one who dismissed it.

= 1.0.10 =
* Fixed the follow-up email subjects and bodies being stuck in English. The packaged wording lived in a config file rather than in a translatable string, so it never reached the translation files, and the settings screen wrote that English back into the database whenever a field was left blank. A store running in Polish, German or Spanish mailed its customers in English no matter how complete the language pack was.
* The subject and body fields now ship empty and show the translated default in grey. Leave a field blank and the wording follows your store language as soon as a translation for it exists; type your own and it is used exactly as typed. Translations are delivered by WordPress.org language packs rather than bundled in this download, so a blank field stays English until a pack is published. Wording you had already customised is left alone. A stored template that is still word for word the old English default is cleared on update, so it starts following your store language.

= 1.0.9 =
* Renamed to Plogins Followup - Follow-Up Emails for WooCommerce so the name leads with the brand rather than a generic word, which is what the WordPress.org plugin review team asks for. The plugin slug is unchanged.

= 1.0.8 =
* Removed the "Tested up to" header from the main PHP file. It belongs in readme.txt only, where it is already declared; present in both, the header can override the readme and show a compatibility version that was never intended.

= 1.0.7 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.0.6 =
* Fixed the PRO promo on the settings screen quoting a price in PLN. PRO is priced and charged in EUR, so an admin on a Polish site was shown a zloty amount and then billed in euro, and the zloty figure was a fixed conversion that drifted from the real charge as the rate moved. The promo now shows the euro price that is actually taken.

= 1.0.4 =
* Translations: completed Polish, German and Spanish for the PRO upgrade panel.

= 1.0.3 =
* Fixed low-contrast admin headings under an OS dark-mode preference.

= 1.0.2 =
* Added bundled Polish, German and Spanish translations for the plugin interface.

= 1.0.1 =
* First stable release.

= 0.1.5 =
* Renamed to Plogins Followup for WooCommerce for a more distinctive plugin name.

= 0.1.4 =
* `followup/email_links` filter exposes URLs discovered in the final follow-up body for PRO engagement tracking.

= 0.1.3 =
* `followup/should_send` filter before a follow-up claims an order, so PRO can defer sends to a chosen hour or weekday.

= 0.1.2 =
* Fire `followup/email_sent` after a follow-up is accepted by wp_mail for PRO send reporting.
* Document the `{coupon}` placeholder for Followup Pro coupon blocks.

= 0.1.1 =
* Add the `followup/sequence_steps` extension filter so add-ons can append custom post-purchase email steps.

= 0.1.0 =
* Initial release: thank-you and review request follow-up emails with per-type enable, trigger status, delay and templates; idempotent daily sender.
