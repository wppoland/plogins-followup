# Followup - Order Follow-Up Emails for WooCommerce

Followup sends automated post-purchase emails to your WooCommerce customers a configurable number of days after an order reaches a status such as Completed. Use it to thank buyers and ask for reviews — all on autopilot.

## Features

- Two ready-to-use email types: thank-you and review request.
- Per type, control whether it is enabled, which order status triggers it, the delay in days, and the subject and body.
- Templates support `{customer}`, `{order}` and `{site}` placeholders.
- A daily background task finds due orders and sends the emails via `wp_mail`.
- Sending is idempotent: the same follow-up is never sent twice for the same order, so overlapping runs are safe.
- A global From name and email for all messages.

## Installation

1. Upload the plugin to `/wp-content/plugins/followup`, or install it via **Plugins → Add New**.
2. Activate it. WooCommerce must be active.
3. Go to **WooCommerce → Follow-ups** to enable email types and edit the templates.

## Frequently Asked Questions

**When are emails actually sent?**
A daily event checks for orders that have been in the configured status for at least the configured number of days, and sends any that have not been sent yet.

**Will a customer ever get the same email twice?**
No. Each follow-up type is recorded against the order once it is sent, so it is never sent again for that order.

Built by WPPoland — https://plogins.com

License: GPL-2.0-or-later
