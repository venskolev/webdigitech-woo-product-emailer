# WebDigiTech Woo Product Emailer

A professional WooCommerce plugin that sends product-specific emails to customers after a successful order, with retry, recovery, logging, and admin tooling built in.

## Overview

WebDigiTech Woo Product Emailer extends WooCommerce with a product-driven email delivery flow. Instead of sending only the standard WooCommerce order emails, this plugin allows each product to carry its own email template. After an eligible paid order reaches an allowed status, the plugin evaluates the order items, resolves the correct template, replaces placeholders, and sends a dedicated email to the customer.

The plugin is designed with operational safety in mind. It includes idempotent dispatch tracking, retry handling for failed sends, recovery scanning for missed eligible orders, database logging, admin diagnostics, template fallback support, and multilingual readiness.

## Main Features

- Product-level email templates inside WooCommerce product edit screens
- Automatic email sending after eligible order events
- Support for allowed order status filtering
- Placeholder replacement with order and product data
- Dispatch log table for tracking every send attempt
- Duplicate-send protection through a unique dispatch key
- Retry flow for failed sends
- Recovery scan for recent paid orders that may have been missed
- Admin pages for settings, logs, tools, and system status
- Manual tools for testing and maintenance
- Fallback template support when a product template is unavailable
- English as the default language, with Bulgarian translation support

## Requirements

- WordPress 6.0 or newer recommended
- WooCommerce active and working
- PHP 8.0 or newer recommended
- MySQL or MariaDB supported by the active WordPress installation
- wp-cron enabled, or a real server cron that triggers WordPress cron events reliably

## Plugin Structure

```text
webdigitech-woo-product-emailer/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── product-panel.css
│   └── js/
│       ├── admin.js
│       └── product-panel.js
├── includes/
│   ├── API/
│   │   └── AjaxTestEmail.php
│   ├── Admin/
│   │   ├── Admin.php
│   │   ├── FooterRenderer.php
│   │   ├── LogsPage.php
│   │   ├── SettingsPage.php
│   │   ├── SystemStatusPage.php
│   │   └── ToolsPage.php
│   ├── Database/
│   │   ├── Migrations.php
│   │   └── Schema.php
│   ├── Product/
│   │   ├── ProductEmailFields.php
│   │   ├── ProductEmailMeta.php
│   │   └── ProductTemplateValidator.php
│   ├── Woo/
│   │   ├── Hooks.php
│   │   ├── OrderListener.php
│   │   └── OrderQuery.php
│   ├── Autoloader.php
│   ├── Capabilities.php
│   ├── Constants.php
│   ├── Cron.php
│   ├── Dependencies.php
│   ├── DispatchRepository.php
│   ├── Eligibility.php
│   ├── Helpers.php
│   ├── Installer.php
│   ├── Logger.php
│   ├── Mailer.php
│   ├── OrderEmailProcessor.php
│   ├── PlaceholderResolver.php
│   ├── Plugin.php
│   ├── TemplateResolver.php
│   └── Uninstaller.php
├── languages/
│   ├── webdigitech-woo-product-emailer.pot
│   ├── webdigitech-woo-product-emailer-en_US.po
│   └── webdigitech-woo-product-emailer-bg_BG.po
├── uninstall.php
└── webdigitech-woo-product-emailer.php
```

## How the Sending Flow Works

### 1. WooCommerce order event is detected
The Woo layer listens for relevant order lifecycle events and passes the order ID into the plugin processor.

### 2. Order eligibility is checked
The plugin verifies that:
- the plugin is enabled
- the order exists
- the order status is allowed
- the order contains valid line items
- the target product is eligible for email sending

### 3. Each order item is processed separately
Every line item is evaluated independently. This makes it possible for one order to send multiple product-specific emails.

### 4. A dispatch key is generated
The plugin creates a stable dispatch key based on the order, order item, product, and email type. This prevents duplicate sends for the same item.

### 5. Template resolution runs
The plugin tries to resolve the correct email template from the product-level configuration. If needed and enabled, it may fall back to the configured fallback template.

### 6. Placeholders are replaced
Template subject, heading, HTML, and plain-text bodies are processed so customer and order information can be injected into the message.

### 7. A dispatch log record is created or updated
The dispatch repository writes the current state into the dispatch table before the actual send attempt.

### 8. Email sending is attempted
The mailer sends the email to the WooCommerce billing email address or another validated target if configured by the flow.

### 9. The result is recorded
- On success, the dispatch is marked as `success`
- On failure, the dispatch is marked as `failed`, attempts are increased, and `next_retry_at` may be scheduled
- On ineligible cases, the dispatch may be marked as `skipped`

## Retry and Recovery

### Retry Flow
Failed sends are not ignored. The plugin stores the last error message, increments the attempt counter, and schedules the next retry time based on the configured retry interval.

The retry cron job reads candidates from the dispatch table where:
- `send_status = failed`
- `next_retry_at` is due
- `attempts` is below the configured retry limit

### Recovery Flow
Recovery scanning exists for cases where an order should have received a product email but the original trigger was missed or interrupted. The recovery cron job scans recent paid orders within a configurable lookback window and re-runs processing.

This is especially useful for:
- temporary mail delivery failures
- server interruptions during checkout completion
- delayed order lifecycle transitions
- manual recovery after operational incidents

## Database Logging

The plugin uses a dedicated dispatch log table to track product email delivery. Important fields include:

- `dispatch_key`
- `order_id`
- `order_item_id`
- `product_id`
- `variation_id`
- `customer_email`
- `template_source`
- `template_identifier`
- `email_subject`
- `email_heading`
- `email_body_html`
- `email_body_text`
- `trigger_source`
- `send_status`
- `attempts`
- `last_error`
- `payload_hash`
- `sent_at`
- `last_attempt_at`
- `next_retry_at`
- `created_at`
- `updated_at`

This log is the operational backbone of the plugin. It enables diagnostics, manual review, retry handling, and cleanup.

## Admin Pages

### Settings
The settings page controls the operational behavior of the plugin, including:
- enable or disable the plugin globally
- allowed WooCommerce order statuses
- retry settings
- recovery settings
- logging behavior
- sender overrides
- fallback template configuration
- content type behavior

### Logs
The logs page displays dispatch records and makes troubleshooting much easier. It is intended for operational review, support work, and validation during testing.

### Tools
The tools page is intended for maintenance actions such as test routines, utility actions, and operational checks.

### System Status
The system status page shows the current environment and plugin state so administrators can quickly verify whether the plugin is ready to operate.

## Product-Level Templates

Each WooCommerce product can define its own email template data. This allows highly specific post-purchase communication per product.

Typical use cases include:
- access instructions for a digital product
- onboarding emails for a service purchase
- product-specific download details
- post-purchase informational guides
- license or account activation instructions

## Fallback Template

If product-specific content is unavailable and fallback is enabled, the plugin can use a centrally managed fallback template.

A valid fallback template should contain:
- a subject
- either HTML or plain-text body content

The plugin also supports generating a plain-text fallback from HTML when needed.

## Internationalization

The plugin is prepared for translation and uses the text domain:

```text
webdigitech-woo-product-emailer
```

Current localization setup:
- English is the default language
- Bulgarian is supported through translation files
- a POT template is included for future translations

## Installation

1. Upload the plugin folder to `wp-content/plugins/`
2. Activate the plugin from the WordPress admin area
3. Ensure WooCommerce is active
4. Open the plugin settings page
5. Configure allowed statuses, retry settings, logging, and fallback template behavior
6. Open a WooCommerce product and define its product email template
7. Run a test order
8. Verify the result in the logs page

## Recommended First-Time Setup

After activation, configure these items first:
- confirm the plugin is enabled
- confirm allowed order statuses match your checkout flow
- enable logging during initial rollout
- configure retry interval and retry count
- configure the fallback template
- test at least one successful and one intentionally failed scenario

## Testing Checklist

Use this checklist before going live:
- plugin activates without PHP errors
- database table is created correctly
- admin pages load without fatal errors
- product template fields save correctly
- a paid order triggers processing
- a successful send becomes `success`
- a forced send failure becomes `failed`
- retry cron can pick up failed items
- recovery cron can re-check recent orders
- cleanup cron removes old log rows according to retention rules
- uninstall behavior matches the preserve-data setting

## Troubleshooting

### No email is sent
Check the following:
- WooCommerce is active
- the plugin is enabled
- the order status is allowed
- the product has a valid template or fallback is enabled
- the customer email address is valid
- WordPress email delivery is working
- the logs page contains a record for the attempted dispatch

### Emails fail repeatedly
Check:
- your mail transport configuration
- the stored `last_error` value in the dispatch log
- whether retry settings are too strict or too aggressive
- whether the email body or subject content is being sanitized into an empty state

### Recovery does not find orders
Check:
- whether recovery is enabled
- the configured lookback window
- whether the target order statuses are included in the allowed status list
- whether the order was already sent successfully and therefore skipped as a duplicate

## Security and Data Handling

The plugin follows standard WordPress patterns for:
- capability checks
- admin-side data sanitization
- email address validation
- template sanitization
- controlled database access through the repository layer

Sensitive operational behavior is centralized through helpers, constants, eligibility checks, and the dispatch repository so the send flow remains predictable and auditable.

## Uninstall Behavior

The plugin includes uninstall support. Depending on the configured setting, it may preserve operational data or remove plugin data during uninstall.

Before uninstalling on production systems, verify whether dispatch logs and settings should be retained.

## Development Notes

The plugin is organized around separated responsibilities:
- constants and defaults in `Constants.php`
- environment and dependency checks in `Dependencies.php`
- installation and schema management in `Installer.php` and `Database/`
- business logic in `OrderEmailProcessor.php`
- persistence in `DispatchRepository.php`
- mail transport in `Mailer.php`
- template resolution in `TemplateResolver.php`
- placeholder replacement in `PlaceholderResolver.php`
- admin UI in `includes/Admin/`
- WooCommerce integration in `includes/Woo/`

This structure is intentional and keeps the plugin maintainable, testable, and easier to extend.

## License

This plugin is part of the WebDigiTech tooling ecosystem.

License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
