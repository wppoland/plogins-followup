# Followup - Order Follow-Up Emails for WooCommerce

Automated post-purchase emails for WooCommerce: **thank-you**, **review request**, **cross-sell** and **win-back**, sent a configurable number of days after an order reaches a chosen status.

Self-contained (no third-party runtime dependencies). FREE, wp.org-ready MVP. The premium add-on lives in [`followup-pro`](https://github.com/wppoland/followup-pro).

## How it works

- A daily wp-cron event (`followup_daily_event`) finds orders that have been in the configured trigger status for at least the configured delay (in days) and have not yet received that follow-up.
- Emails are sent via `wp_mail` with `{customer}` / `{order}` / `{site}` placeholders rendered.
- **Idempotent:** each follow-up type is recorded against the order (`_followup_sent_{type}` meta) before sending, so the same email is never sent twice — safe across overlapping cron runs.

## Admin

WooCommerce → **Follow-ups**. Per type: enable, trigger order status, delay (days), subject and body. Plus a global From name/email.

## Architecture

- `followup.php` — bootstrap; schedules/clears the cron on activation/deactivation; boots on `init:0` and fires `do_action('followup/booted', Plugin::instance())`.
- `src/Plugin.php` — DI container + boot order from `config/services.php` and `config/hooks.php`.
- `src/Service/Scheduler.php` — the daily cron worker (due-order discovery + idempotent send).
- `src/Service/Mailer.php` — placeholder rendering + `wp_mail`.
- `src/Admin/SettingsPage.php` — WooCommerce submenu settings page.
- `src/Settings.php`, `src/FollowupTypes.php` — settings access and the type registry (filterable via `followup/types`).

## Extension points

- `followup/booted` (action) — fired after boot; the PRO add-on hooks here.
- `followup/types` (filter) — register additional follow-up types.
- `followup/mail` (filter) — modify the rendered email before sending.
- `followup/sent` (action) — fired after a follow-up is sent.

## Development

```bash
composer install
composer cs        # PHPCS
composer analyse   # PHPStan level 6
```

License: GPL-2.0-or-later.
