# BD Subscription Mailer

Lightweight automated emails for WooCommerce Subscriptions sites. Replaces AutomateWoo for three workflows:

1. **Task Reminder** — an email after each successful subscription payment (per-product, toggleable per site).
2. **Failed Payment Sequence** — a six-message dunning sequence scheduled from the failure date, auto-cancelled on recovery.
3. **Card Expiry Warnings** — 45 / 20 / 7 day warnings before a stored card expires.

All scheduling uses **Action Scheduler** (bundled with WooCommerce) — no wp-cron jobs. All sending uses `wp_mail()`, so it works with WP Mail SMTP + Postmark out of the box. HPOS compatible.

## Requirements

- WordPress 6.4+, WooCommerce, WooCommerce Subscriptions
- PHP 8.1+ (tested on 8.3 / 8.4)

## Installation

1. Upload `bd-subscription-mailer.zip` via **Plugins → Add New → Upload Plugin** (or unzip into `wp-content/plugins/`).
2. Activate **BD Subscription Mailer**.
3. Go to **WooCommerce → BD Subscription Mailer** to configure.

Activation creates two tables (`{prefix}bdSM_log`, `{prefix}bdSM_expiry_sent`) and schedules the daily 7:00am UTC card-expiry job.

## Updates

The plugin auto-updates from GitHub releases via the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). When a new release is published at [bluedognz/bd-subscription-mailer](https://github.com/bluedognz/bd-subscription-mailer), every site shows the update on **Dashboard → Updates / Plugins** like any other plugin (the zip attached to the release is what gets installed). Only the first install is manual.

To publish an update: bump the `Version` header, commit, push, tag (e.g. `v1.2.1`) and create a GitHub release with the plugin zip attached.

Optional, avoids GitHub API rate limits on sites that check frequently — add to `wp-config.php`:

```php
define( 'BDSM_GH_TOKEN', 'ghp_yourtoken' );
```

## Toggling features per site

Under the **Settings** tab:

| Setting | Notes |
|---|---|
| Enable plugin | Master switch. Off = nothing sends or schedules anywhere. |
| From name / From email | Optional sender name and address for every email the plugin sends. Leave empty for the site default. **If WP Mail SMTP has "Force From" enabled it overrides these** — use a Postmark-authenticated domain. |
| Task Reminder | Per-site toggle. Turn **on** for bluedogdigitalmarketing.com.au, **off** for bluedogwebsites.com. |
| Task Reminder CC | Optional. Every Task Reminder email is CC'd to this address. |
| Failed Payment CC | Optional. Every Failed Payment email (customer and internal) is CC'd to this address. |
| Card Expiry CC | Optional. Every Card Expiry warning is CC'd to this address. |
| Support link URL | The URL inserted by the `{support_link}` tag. |

Each CC field is independent — leave any of them empty for no CC. If a CC address matches the email's main recipient it is skipped to avoid duplicates.

Failed Payment and Card Expiry are always active while the plugin is enabled.

## Editing email content

Each feature tab has WordPress TinyMCE editors:

Each email panel on these tabs can be **collapsed/expanded** by clicking its header (or the arrow), with **Expand all / Collapse all** links at the top — handy when managing many emails at once.

- **Task Reminder** — every published subscription product is listed. Tick the checkbox to enable it, then set a subject and body. Only ticked products trigger emails. A product with no body content is skipped (and logged).
- **Failed Payment** — six messages, each with subject, body and an editable **delay in days** (measured from the original failure timestamp, defaults 1 / 2 / 4 / 7 / 8 / 38). Messages 1–4 always go to the customer. Messages 5–6 have an editable internal recipient field (falls back to the site admin email if empty) — the customer is never sent these.
- **Card Expiry** — three emails (45, 20, 7 days before expiry), each with subject and body.

Changing a delay only affects sequences scheduled **after** the save; already-queued emails keep their original send time (cancel them on the Queue tab if needed).

### Test emails

Every editor has a **Send test email** box underneath it: enter an address and click the button. The test uses whatever is currently in the editor (no need to save first), fills all template tags with sample data, prefixes the subject with `[TEST]`, and sends only to that address — CC settings are not applied.

## Template tags

Type tags directly into subjects or bodies; they're replaced at send time.

**Task Reminder:** `{customer_first_name}` `{subscription_id}` `{product_name}` `{support_link}` `{site_name}`

**Failed Payment (customer, #1–4):** `{customer_first_name}` `{customer_email}` `{subscription_id}` `{order_total}` `{payment_update_link}` `{site_name}` `{customer_domain}`

**Failed Payment (team, #5–6):** `{customer_first_name}` `{customer_email}` `{subscription_id}` `{order_total}` `{customer_domain}` `{site_name}` `{days_overdue}`

**Card Expiry:** `{customer_first_name}` `{customer_email}` `{card_expiry_month}` `{card_expiry_year}` `{payment_update_link}` `{site_name}`

Notes:
- `{customer_domain}` reads the `customer_domain` (or `_customer_domain`) meta on the subscription; blank if not set.
- `{payment_update_link}` is the subscription's change-payment-method URL (falls back to the My Account view-subscription URL).
- `{days_overdue}` is whole days since the original payment failure.

## How the logic works

**Task Reminder** fires on `woocommerce_subscription_payment_complete`, only for enabled products. If the payment is a **recovery** (the plugin flagged a prior failure on that subscription), the reminder is skipped and logged as `skipped`.

**Failed Payment** fires on `woocommerce_subscription_payment_failed` and queues all six messages from the failure timestamp. Before **each** send, the subscription is re-checked:
- payment recovered (active again) → remaining emails cancelled silently;
- subscription cancelled/expired → remaining emails cancelled.
A successful payment also cancels the queue immediately. A repeat failure restarts the sequence from the new failure date.

**Card Expiry** runs daily at 7:00am UTC, on active subscriptions only. Expiry is read from the customer's **saved payment token** (`WC_Payment_Tokens`) — where WooCommerce and the Stripe gateway actually store it — preferring the token the subscription pays with, then the customer's default card, then their most recent card. Order meta (`_stripe_card_expiry_month` / `_stripe_card_expiry_year`, then `_payment_method_expiry_date` as `MM/YY`, `MM/YYYY` or `YYYY-MM`) is used as a fallback. Subscriptions with no card data, and non-card tokens, are skipped. Sent warnings are recorded per subscription **and card expiry date** in `bdSM_expiry_sent`, so duplicates are impossible but a newly saved card gets a fresh set of warnings. If a tier's exact day was missed (site offline etc.), the most urgent applicable warning is sent on the next run instead of being lost.

## Subscription watchdog

A self-contained module (`includes/class-bd-watchdog.php`) that recovers subscriptions which were paid but left stuck **on-hold** — typically a Stripe webhook race or a stale cache that stopped the status update firing.

- Runs on its own recurring **30-minute Action Scheduler** job (same scheduler and group as the email jobs; visible on the Queue tab).
- Each run scans up to 50 on-hold subscriptions, skips any whose latest order is under 15 minutes old, and looks for a renewal order marked completed/processing and paid within the last 6 hours. Matches are switched to **active** with an audit note.
- When anything is fixed it emails a diagnostic report to `admin_email` (filter `bd_watchdog_alert_email` to redirect) and logs a warning to WooCommerce → Status → Logs under source `bd-subscription-watchdog`.

No new tables, no settings — it just runs. It requires WooCommerce Subscriptions (guarded) and is scheduled/cleared automatically on plugin activation/deactivation.

## Copying content between sites

The **Export / Import** tab moves email content between sites without retyping. Export produces a Base64 string covering the **Failed Payment** emails, the **Card Expiry** emails, and (optionally on import) the support link + CC addresses. Paste it into the Import box on the other site and tick which sections to apply — leave **Settings** unticked to keep that site's own CC addresses.

Task Reminder content is deliberately excluded: it is keyed by product ID, which differs per site. The plugin-enable and Task Reminder toggles are never imported either, so a site's on/off state is always preserved.

## Log & Queue

- **Log** — last 200 events (sent / skipped / cancelled) with date, feature, customer, subscription and message number. Clear button included. The table self-trims to 1,000 rows.
- **Queue** — pending Action Scheduler actions created by this plugin only. Cancel a single queued email, or every queued email for a subscription ID at once.

## Uninstall

Deleting the plugin (not just deactivating) runs `uninstall.php`, which drops both custom tables, deletes all plugin options and meta, and removes every scheduled action in the plugin's Action Scheduler group.

## File structure

```
bd-subscription-mailer/
├── bd-subscription-mailer.php        Main plugin file / bootstrap
├── uninstall.php                     Full cleanup on delete
├── readme.md
├── includes/
│   ├── bdsm-functions.php            Helpers, defaults, settings access
│   ├── class-bdsm-install.php        Tables, activate/deactivate
│   ├── class-bdsm-logger.php         bdSM_log table
│   ├── class-bdsm-mailer.php         Tag replacement + HTML wp_mail()
│   ├── class-bdsm-task-reminder.php  Feature 1
│   ├── class-bdsm-failed-payment.php Feature 2
│   └── class-bdsm-card-expiry.php    Feature 3
├── admin/
│   ├── class-bdsm-admin.php          Menu, tabs, save/cancel handlers
│   └── views/                        One view per tab
└── templates/
    └── email-wrapper.php             HTML email shell (edit to restyle)
```
