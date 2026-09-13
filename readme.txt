=== Plogins Followup - Follow-Up Emails for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send automated post-purchase emails for WooCommerce: thank-you and review requests, a set number of days after an order.

== Description ==

Followup sends automated post-purchase emails to your WooCommerce customers, a configurable number of days after an order reaches a status such as Completed.

Two email types come ready to use:

* **Thank-you**: a short note shortly after the order is fulfilled.
* **Review request**: asks for a review once the customer has had the product for a while.

For each type you set whether it is enabled, which order status triggers it, how many days to wait, and the subject and body. Subjects and bodies support `{customer}` (first name), `{order}` (order number) and `{site}` (site name).

A daily wp-cron event picks up orders that are due and sends the emails with `wp_mail`, so they use whatever mail setup the site already has. The order is marked before the message is handed over rather than after, so a send that dies halfway through cannot leave the follow-up looking unsent and mail it again every day.

Only orders placed after the plugin started running on your site are followed up, so switching it on in a shop with years of orders behind it does not mail those customers. Each run sends at most 200 emails per follow-up type, oldest orders first, so a large shop catches up over several days rather than in one burst.

Developers can extend the sequence through the `followup/sequence_steps` filter. Each custom step can provide its own trigger status, delay, subject and body while reusing Followup's send-once scheduler.

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

A daily wp-cron event checks for orders that have been in the configured status for at least the configured number of days, and sends any that have not been sent yet. It is wp-cron, so it runs on site traffic; if your site defines `DISABLE_WP_CRON` without a system cron job calling `wp-cron.php`, nothing is sent at all.

= Will it email my existing customers when I activate it? =

No. Activation records the moment you switched the plugin on, and orders placed before that are never followed up.

Switch the plugin off and on again and that cut-off moves forward, because nothing is sent while it is off. Each email type then reaches back by its own delay and no further: with the packaged settings the thank-you covers orders from the last day and the review request orders from the last 7 days, counted from the moment you switched it back on. Everything that piled up before that is left alone.

On a site with no cut-off recorded at all, it is the first time the daily task runs there. That covers a site in a multisite network you did not activate on directly, and a site updating from version 1.0.11 or earlier.

= How many emails can one run send? =

At most 200 per follow-up type, per daily run, oldest orders first. The rest wait for the following run.

That 200 counts emails that went out. An order that is read and cannot be sent, because an add-on holds it back or your mail server refuses the address, spends none of it: the run reads on past it, up to 1,000 orders per type. A run that ends still inside a stretch of orders it cannot send to records where it stopped, and the next one starts there.

= Will a customer ever get the same email twice? =

Practically no, and both of the ways it can happen need a send to die after your mail server has already taken the message. The order is marked before the email is handed to `wp_mail`, so an ordinary refusal or failure is never mistaken for a send. A PHP fatal error or a script timeout in the middle of `wp_mail` is different: the plugin cannot tell whether the message went out, so it retries that one order exactly once and then leaves it alone, and that retry is a second copy if the first one did arrive. The other way is two cron runs overlapping in the moment between one of them marking an order and the other one reading it.

We chose it that way round on purpose. Marking the order afterwards instead would turn the same crash into a follow-up that is silently never sent, and a missing email you never hear about is worse than a duplicate you do.

= Which placeholders can I use? =

`{customer}` (first name), `{order}` (order number) and `{site}` (your site name), in both the subject and body.

= Which order statuses can trigger a follow-up? =

You choose the trigger status per email type (for example processing or completed) and the delay in days before it sends.


= Does this plugin work on WordPress Multisite? =

Yes. This plugin is compatible with WordPress Multisite. Network activate it or activate it on individual sites; each site keeps its own settings, its own data and its own cut-off date. WordPress only runs an activation step on the site you were on when you clicked, so on the other sites the cut-off is the first time the daily task runs there, and no order placed before that is followed up.

== Screenshots ==

1. The Follow-ups settings screen: enable each email type and edit its trigger status, delay and templates.

== External Services ==

Followup does not connect to any external services. It has no API keys, sends no data off-site, and loads nothing from a remote URL or CDN. Everything runs on your own WordPress install: settings are stored in the `followup_settings`, `followup_db_version`, `followup_install_floor`, `followup_reactivated_at` and `followup_scan_offset` options, and each follow-up is recorded as `_followup_sent_{type}` order meta, first as a claim while it is being sent and then as the date it went out. Emails go out through your site's own `wp_mail()` using your WooCommerce store sender, so they travel by whatever mail setup you already have.

== Translations ==

Plogins Followup is fully translatable and ships the `plogins-followup.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

= 1.0.14 =
* Fixed re-activation handing the short follow-ups the longest delay's reach-back. Switching the plugin back on moved the cut-off forward by the longest delay configured anywhere in the sequence, and that one cut-off applied to every email type, so with the packaged settings the 7 day review request gave the thank-you a week of orders to thank people for, and a 30 day step added by Plogins Followup Pro gave it a month. Each type now reaches back by its own delay: on the packaged settings the thank-you covers the last day, the review request the last 7 days.
* Fixed a shop that cannot deliver stopping its follow-ups for good. The 200-per-run ceiling counted orders read rather than emails sent, so 200 orders that could not be sent, held back by an add-on or refused by the mail server, filled it every day and the orders behind them were never reached. The ceiling now counts emails: an order that cannot be sent costs nothing, the run reads on past it up to 1,000 orders per type, and a run that ends inside such a stretch records where it stopped so the next one carries on from there.

= 1.0.13 =
* Fixed the cut-off staying pinned to your very first install. Switching the plugin off stops every follow-up, because deactivating removes the daily task, but the cut-off did not move, so switching it back on months later handed the first run every order taken in between. A shop that installed the plugin, turned it off the next day and turned it on a year later mailed 200 thank-yous a day until that year of orders ran out. Re-activating now moves the cut-off forward to the longest delay you have configured, 7 days on the packaged settings: orders still inside that window are followed up, the rest are left alone.
* Fixed network activation on multisite mailing old orders. WordPress runs the activation step only on the site you were on when you clicked, so every other site in the network had no cut-off and took one that reached a week into the past, then mailed those customers. A site with no cut-off recorded, which is any site the activation step never reached and any site updating from 1.0.11 or earlier, now takes the moment the sender first runs there, and follows up nothing older. On a site updating from 1.0.11 that is stricter than 1.0.12 promised: a follow-up that was due in the last few days is skipped rather than sent late, which is the side we would rather be wrong on.
* Fixed a follow-up going out for an order that had left the status that triggers it. A send interrupted by a crash is retried the next day, and that retry was not checking the status again, so an order refunded or cancelled in between was still thanked for its purchase.
* Fixed the sender jamming when an add-on holds sends back. Plogins Followup Pro can defer a send to a chosen hour or day. An order that was held back after a crash had claimed it was counted against the 200-per-run ceiling every day without ever being resolved, and 200 of them filled the ceiling, at which point the shop stopped sending follow-ups entirely and said nothing. A held-back order now gives its claim back and is picked up again when it is allowed to send.
* Fixed one mail failure burying a follow-up for good. If a send crashed and the retry the next day then hit an ordinary failure, a mail server refusing the message or being down for a minute, the follow-up was marked spent even though nothing had been sent, and it was never tried again. A refusal is now what it is, a message that did not go out, so the next run tries again.
* Corrected the claim that a follow-up is "never sent twice". It is sent once per order, and the two ways a customer can see it twice both need a send to die after the mail server has already taken the message. The FAQ now says which trade-off this plugin makes and why: the order is marked before the email is handed over, which turns a crash into a possible duplicate rather than into a follow-up that is silently never sent.
* On the settings screen, the cut-off date no longer describes itself as the day you switched the plugin on. On a site that inherited the plugin, or one updating from an older version, it is the day the sender first ran there.

= 1.0.12 =
* Fixed the worst thing this plugin could do: on a shop that already had orders, the first daily run after activation treated the entire order history as due and started mailing customers who had ordered months or years earlier, 200 per follow-up type per day until the backlog drained. Activation now records the moment you switched the plugin on, and orders placed before it are never followed up. Sites updating from an earlier version get that floor set at their longest configured delay, which keeps the follow-ups still legitimately pending and leaves the rest of the history alone.
* Fixed follow-ups being dropped for good when a send died mid-flight. The order is marked before the email is handed to `wp_mail` so an overlapping run cannot send it twice, but a PHP fatal error or a script timeout inside `wp_mail` left that mark behind with no email sent and nothing to undo it. An unfinished send is now recognised as unfinished and retried once, then left alone, so one crash can neither lose a follow-up silently nor mail the same customer every day.
* The delay is now counted to the second rather than to the day. WooCommerce reads a plain date as a whole day, so a follow-up could go out up to a day earlier than the delay you set. It now never goes out early, which means the first send after this update can land up to a day later than you are used to.
* The settings screen and the listing now state the ceiling: at most 200 emails per follow-up type per daily run.

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
