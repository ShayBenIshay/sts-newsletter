# STS Newsletter

A customer club / newsletter for WooCommerce with WhatsApp and email subscriptions, an admin screen for sending campaigns over WhatsApp, and unsubscribe handling. The admin UI is in Hebrew.

## Features
- Sign-up fields at checkout, on the registration form, in user profiles, and a footer form.
- Phone normalisation to international format (Israeli mobile numbers).
- Admin under **מועדון לקוחות** to compose and send a campaign.
- Unsubscribe handling.
- WhatsApp connection panel in the admin: create a session, show the QR code, check status, disconnect, set a daily marketing cap. Secrets are stored encrypted.

## Footer form
Add `do_action( 'sts_newsletter_footer_form' );` in your theme where the form should appear.

## WhatsApp gateway
WhatsApp sending needs a compatible HTTP gateway. The plugin calls session-based routes (`/sessions`, `/sessions/{id}/qr`, `/sessions/{id}/campaigns/{id}` and similar) with an `x-api-key` header, against the API base URL set in the plugin settings. That API is not included here, so use your own service that implements these routes.

## Requirements
WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+.

## Status

Version 1.0.0. Provided as-is, under GPL-2.0-or-later (see `LICENSE`). Published without full functional testing on a live store; try it on staging first.

Setup, customization and support are available from [Shay Tech Solutions](https://shaytechsolutions.com).
