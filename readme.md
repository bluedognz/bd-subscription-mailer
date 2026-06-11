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

## Toggling features per site

Under the **Settings** tab:

| Setting | Notes |
|---|---|
| Enable plugin | Master switch. Off = nothing sends or schedules anywhere. |
| Task Reminder | Per-site toggle. Turn **on** for bluedogdigitalmarketing.com.au, **off** for bluedogwebsites.com. |
| Task Reminder CC | Optional. Every Task Reminder email is CC'd to this address. |
| Failed Payment CC | Optional. Every Failed Payment email (customer and internal) is CC'd to this address. |
| Card Expiry CC | Optional. Every Card Expiry warning is CC'd to this address. |
| Support link URL | The URL inserted by the `{support_link}` tag. |

Each CC field is independent — leave any of them empty for no CC. If a CC address matches the email's main recipient it is skipped to avoid duplicates.

Failed Payment and Card Expiry are always active while the plugin is enabled.

## Editing email content

Each feature tab has WordPress TinyMCE editors:

- **Task Reminder** — every published subscription product is listed. Tick the checkbox to enable it, then set a subject and body. Only ticked products trigger emails. A product with no body content is skipped (and logged).
- **Failed Payment** — six messages, each with subject, body and an editable **delay in days** (measured from the original failure timestamp, defaults 1 / 2 / 4 / 7 / 8 / 38). Messages 1–4 always go to the customer. Messages 5–6 have an editable internal recipient field (falls back to the site admin email if empty) — the customer is never sent these.
- **Card Expiry** — three emails (45, 20, 7 days before expiry), each with subject and body.

Changing a delay only affects sequences scheduled **after** the save; already-queued emails keep their original send time (cancel them on the Queue tab if needed).

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

**Card Expiry** runs daily at 7:00am UTC, on active subscriptions only. Expiry is read from `_stripe_card_expiry_month` / `_stripe_card_expiry_year` on the subscription or its parent order, with `_payment_method_expiry_date` (`MM/YY`, `MM/YYYY` or `YYYY-MM`) as fallback; subscriptions with no card data are skipped. Sent warnings are recorded per subscription **and card expiry date** in `bdSM_expiry_sent`, so duplicates are impossible but a newly saved card gets a fresh set of warnings. If a tier's exact day was missed (site offline etc.), the most urgent applicable warning is sent on the next run instead of being lost.

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
