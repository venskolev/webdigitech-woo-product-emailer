=== WebDigiTech Woo Product Emailer ===
Contributors: webdigitech
Tags: woocommerce, email, order email, product email, automation, digital products
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sends product-specific customer emails after successful WooCommerce orders, with retry logic, recovery scan, fallback templates, dispatch logging, and admin tools.

== Description ==

WebDigiTech Woo Product Emailer is a professional WooCommerce automation plugin that sends customer emails based on purchased products after a successful order flow.

The plugin is designed for stores that sell digital products, services, access links, instructions, onboarding materials, custom product follow-ups, or any other post-purchase content that must be delivered automatically after payment.

Main features:

* Product-specific email sending after eligible WooCommerce orders.
* Support for allowed WooCommerce order statuses.
* Central dispatch logging with duplicate-send protection.
* Retry logic for failed deliveries.
* Recovery scan for recently paid orders.
* Global fallback email template when product-level template is not available.
* Admin settings screen for plugin behavior and delivery options.
* Manual tools for retry, recovery, and maintenance workflows.
* English default UI with translation-ready architecture.

This plugin is intended for professional WooCommerce environments where reliability, traceability, and controlled email delivery matter.

== Features ==

= Order-aware delivery flow =

The plugin listens to WooCommerce order events and processes eligible orders and order items based on configured rules.

= Product-specific content =

Each purchased product can resolve its own email content. If product-specific content is unavailable, the plugin can optionally use a fallback template.

= Duplicate-send protection =

Each send attempt is tracked with a dispatch key so the same order item is not sent repeatedly after a successful delivery.

= Retry handling =

If an email fails, the plugin can mark the dispatch as failed and schedule it for retry according to the configured retry interval and maximum attempts.

= Recovery scan =

The recovery task can scan recent paid orders and re-process them. This helps recover missed sends caused by temporary issues, hook timing problems, or interrupted requests.

= Dispatch logs =

Every send flow can be tracked through the internal dispatch log table, including status, attempts, timestamps, payload metadata, and errors.

= Translation-ready =

The plugin is prepared for English by default and Bulgarian as an optional translation.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress Plugins screen.
3. Make sure WooCommerce is installed and active.
4. Open the plugin settings page from the WordPress admin area.
5. Configure the allowed order statuses, retry behavior, logging, fallback template, and sender overrides if needed.
6. Test with a real or staging WooCommerce order.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active.

= When are emails sent? =

Emails are sent when an order becomes eligible according to the configured logic and allowed statuses.

= Can I prevent duplicate sends? =

Yes. The plugin uses dispatch keys and success-state tracking to avoid sending the same email repeatedly for the same order item.

= What happens if email delivery fails? =

The plugin can log the failure, increment attempts, and schedule a retry if retry handling is enabled.

= What is the recovery scan used for? =

Recovery scan checks recent eligible orders and re-processes them. It is useful when an order should have triggered an email but did not complete the send flow for any reason.

= Can I use a fallback template? =

Yes. If fallback email handling is enabled and a valid fallback template exists, the plugin can use it when product-specific content is not available.

= Is the plugin translation-ready? =

Yes. The plugin is built with a translation-ready text domain and can load English and Bulgarian language files.

== Screenshots ==

1. Plugin settings screen.
2. Dispatch log overview.
3. Retry and recovery workflow.
4. Fallback email configuration.

== Changelog ==

= 1.0.0 =
* Initial stable release.
* Product-based customer email delivery for WooCommerce orders.
* Dispatch repository and duplicate-send protection.
* Retry cron for failed sends.
* Recovery scan cron for recent eligible orders.
* Cleanup cron for old dispatch logs.
* Fallback email template support.
* Admin settings and maintenance tools.
* Translation-ready structure with English default.

== Upgrade Notice ==

= 1.0.0 =
Initial production release.
