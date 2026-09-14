# Changelog

All notable changes to BD Subscription Mailer are documented here.

## 1.12.1 — 2026-09-14
- Added a full CHANGELOG.md so the plugin update screen's Changelog tab shows the complete version history, not just the latest release.

## 1.12.0 — 2026-09-14
- Queue tab now shows the customer email for each pending action (mailto link), so you can quickly find and cancel a specific customer's failed-payment sequence — e.g. when they ask to pause reminders.

## 1.11.0 — 2026-08-13
- Added an optional "Sent via Subscription Mailer, by Blue Dog Software" promo footer to outgoing emails (on by default).

## 1.10.0 — 2026-07-21
- Live Stripe expiry lookup: card expiry is fetched from the Stripe API using the WooCommerce Stripe secret key and cached on the subscription. The daily job refreshes before evaluating; the Cards tab has a manual "Refresh card data from Stripe" button (read-only, sends no emails). Data priority is now Stripe → saved token → order meta, and each Cards row shows its source.
- Stale-card detection: if a payment succeeded after a card's stated expiry, the stored date is provably out of date (account-updater replacement). Those rows show as "Stale — card replaced" and are never emailed.
- Warning shown on the Cards tab when no Stripe secret key is configured.

## 1.9.0 — 2026-07-21
- New Cards tab: an overview of every active subscription and the card it will be charged against, using the same lookup as the daily job so it doubles as a diagnostic. Shows brand/last4, expiry, days remaining, colour-coded status, and which warning tiers were already sent. Includes a "Run expiry check now" button.

## 1.8.0 — 2026-07-21
- Bug fix: Card Expiry warnings never sent. The plugin only looked for `_stripe_card_expiry_*` order meta, which the WooCommerce Stripe gateway does not write, so every subscription was silently skipped. Card expiry is now read from the customer's saved payment token (`WC_Payment_Tokens`), with the original order-meta lookups kept as a fallback.

## 1.7.1 — 2026-06-22
- Watchdog now runs on Action Scheduler (recurring 30-minute action in the plugin group) instead of WP-Cron, consistent with the rest of the plugin, and appears on the Queue tab. Cleans up any legacy WP-Cron event from v1.7.0.

## 1.7.0 — 2026-06-22
- New subscription watchdog: auto-activates on-hold subscriptions whose renewal was actually paid (recovers from Stripe webhook races / stale-cache lock-ups), emails a diagnostic report to the admin, and logs to WooCommerce → Status → Logs (source `bd-subscription-watchdog`). Self-contained module, no new tables.

## 1.6.0 — 2026-06-20
- Customisable From name and From email in Settings, applied to every email the plugin sends (including test emails). Leave empty for the site default. (WP Mail SMTP "Force From" overrides these.)

## 1.5.0 — 2026-06-13
- Collapsible email panels on the Task Reminder, Failed Payment and Card Expiry tabs, with Expand all / Collapse all links. Failed Payment headers show the delay when collapsed.
- Plugin icon now appears on the Updates and Plugins screens.

## 1.4.0 — 2026-06-13
- New Export / Import tab: copy Failed Payment and Card Expiry emails (and optionally the support link + CC addresses) between sites via a Base64 string. Selective import with per-section checkboxes; Task Reminder content (product-keyed) and the enable toggles are never exported or imported.

## 1.3.0 — 2026-06-11
- Send test email box under every email editor (Task Reminder, Failed Payment, Card Expiry). Tests use the current editor content, sample data for all template tags, a [TEST] subject prefix, and skip CC.

## 1.2.0 — 2026-06-11
- GitHub auto-updates via the bundled Plugin Update Checker — sites update from Dashboard → Updates from this release onward. Optional `BDSM_GH_TOKEN` for private repos / rate limits.
- Author updated to Blue Dog Digital.

## 1.1.0 — 2026-06-11
- Three independent CC fields (Task Reminder, Failed Payment, Card Expiry) in Settings.
- Two-column layout for the Task Reminder editors.
- Removed "(Feature 1)" from the Task Reminder settings heading.

## 1.0.0 — 2026-06-11
- Initial release. Three automated email workflows for WooCommerce Subscriptions — Task Reminder (per-product, after successful payment), Failed Payment sequence (6 messages with auto-cancel on recovery), and Card Expiry warnings (45/20/7 days). Action Scheduler for all scheduling, HTML email via `wp_mail()`, admin UI with Log and Queue tabs, and full uninstall cleanup.
