=== BD Subscription Mailer ===
Contributors: bluedogdigital
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.12.2
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight automated emails for WooCommerce Subscriptions — task reminders, failed payment sequences and card expiry warnings.

== Description ==

BD Subscription Mailer sends three types of automated email for WooCommerce Subscriptions sites, as a lightweight replacement for AutomateWoo:

1. **Task Reminder** — an email after each successful subscription payment (per-product, toggleable per site).
2. **Failed Payment Sequence** — a six-message dunning sequence scheduled from the failure date, auto-cancelled on recovery.
3. **Card Expiry Warnings** — 45 / 20 / 7 day warnings before a stored card expires, read live from Stripe.

All scheduling uses Action Scheduler (bundled with WooCommerce). All sending uses wp_mail(), so it works with WP Mail SMTP + Postmark. HPOS compatible. Includes a subscription watchdog, a Cards overview, per-editor test emails, export/import between sites, and GitHub auto-updates.

== Changelog ==

= 1.12.2 =
* Added a WordPress-format readme.txt so the plugin update screen's Changelog and Description tabs show the full version history and details.
* Queue tab: subscription links now open in a new tab, with an icon indicating so.

= 1.12.1 =
* Added a CHANGELOG.md to the repository.

= 1.12.0 =
* Queue tab now shows the customer email for each pending action (mailto link), so you can quickly find and cancel a specific customer's failed-payment sequence — e.g. when they ask to pause reminders.

= 1.11.0 =
* Added an optional "Sent via Subscription Mailer, by Blue Dog Software" promo footer to outgoing emails (on by default).

= 1.10.0 =
* Live Stripe expiry lookup: card expiry is fetched from the Stripe API and cached on the subscription. Data priority is now Stripe → saved token → order meta, and each Cards row shows its source. Manual "Refresh card data from Stripe" button added.
* Stale-card detection: cards with a successful payment dated after their stated expiry show as "Stale — card replaced" and are never emailed.

= 1.9.0 =
* New Cards tab: an overview of every active subscription and the card it will be charged against, using the same lookup as the daily job. Includes a "Run expiry check now" button.

= 1.8.0 =
* Bug fix: Card Expiry warnings never sent. Card expiry is now read from the customer's saved payment token (WC_Payment_Tokens) instead of order meta the Stripe gateway never writes.

= 1.7.1 =
* Watchdog moved from WP-Cron to Action Scheduler, consistent with the rest of the plugin; appears on the Queue tab.

= 1.7.0 =
* New subscription watchdog: auto-activates on-hold subscriptions whose renewal was actually paid, emails a diagnostic report, and logs to WooCommerce → Status → Logs.

= 1.6.0 =
* Customisable From name and From email in Settings, applied to every email (including test emails).

= 1.5.0 =
* Collapsible email panels with Expand all / Collapse all.
* Plugin icon now appears on the Updates and Plugins screens.

= 1.4.0 =
* New Export / Import tab: copy Failed Payment and Card Expiry emails between sites via a Base64 string, with per-section checkboxes.

= 1.3.0 =
* Send test email box under every email editor, using sample data and a [TEST] subject prefix.

= 1.2.0 =
* GitHub auto-updates via the bundled Plugin Update Checker. Author updated to Blue Dog Digital.

= 1.1.0 =
* Three independent CC fields (Task Reminder, Failed Payment, Card Expiry).
* Two-column layout for the Task Reminder editors.

= 1.0.0 =
* Initial release. Task Reminder, Failed Payment sequence, and Card Expiry warnings for WooCommerce Subscriptions, with Log and Queue tabs and full uninstall cleanup.
