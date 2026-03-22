<?php
/**
 * Plugin Name: WebDigiTech Woo Product Emailer
 * Plugin URI: https://webdigitech.de
 * Description: Sends product-specific customer emails for paid WooCommerce orders with strict dispatch tracking, fallback templates, logging, and recovery support.
 * Version: 1.0.24
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Author: WebDigiTech - Ventsislav Kolev
 * Author URI: https://webdigitech.de
 * Text Domain: webdigitech-woo-product-emailer
 * Domain Path: /languages
 * WC requires at least: 8.5
 * WC tested up to: 10.2
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

if (defined('WDT_WCPE_FILE')) {
	return;
}

define('WDT_WCPE_FILE', __FILE__);
define('WDT_WCPE_BASENAME', plugin_basename(__FILE__));
define('WDT_WCPE_DIR', plugin_dir_path(__FILE__));
define('WDT_WCPE_URL', plugin_dir_url(__FILE__));
define('WDT_WCPE_VERSION', '1.0.24');
define('WDT_WCPE_TEXT_DOMAIN', 'webdigitech-woo-product-emailer');

/**
 * Декларира съвместимост с WooCommerce features.
 *
 * @return void
 */
function wdt_wcpe_declare_woocommerce_compatibility(): void
{
	if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'custom_order_tables',
		__FILE__,
		true
	);

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'orders_cache',
		__FILE__,
		true
	);
}

add_action('before_woocommerce_init', 'wdt_wcpe_declare_woocommerce_compatibility');

/**
 * Зарежда основните файлове на плъгина.
 *
 * @return void
 */
function wdt_wcpe_bootstrap(): void
{
	$required_files = array(
		WDT_WCPE_DIR . 'includes/Constants.php',
		WDT_WCPE_DIR . 'includes/Autoloader.php',
		WDT_WCPE_DIR . 'includes/Dependencies.php',
		WDT_WCPE_DIR . 'includes/Installer.php',
		WDT_WCPE_DIR . 'includes/Plugin.php',
	);

	foreach ($required_files as $required_file) {
		if (! file_exists($required_file)) {
			add_action(
				'admin_notices',
				static function () use ($required_file): void {
					if (! current_user_can('activate_plugins')) {
						return;
					}

					echo '<div class="notice notice-error"><p>';
					echo esc_html(
						sprintf(
							/* translators: %s: missing file path */
							__('WebDigiTech Woo Product Emailer cannot start because a required file is missing: %s', 'webdigitech-woo-product-emailer'),
							$required_file
						)
					);
					echo '</p></div>';
				}
			);

			return;
		}

		require_once $required_file;
	}
}

/**
 * Зарежда text domain за преводи.
 *
 * @return void
 */
function wdt_wcpe_load_textdomain(): void
{
	load_plugin_textdomain(
		WDT_WCPE_TEXT_DOMAIN,
		false,
		dirname(WDT_WCPE_BASENAME) . '/languages'
	);
}

/**
 * Инициализира плъгина.
 *
 * @return void
 */
function wdt_wcpe_init(): void
{
	wdt_wcpe_bootstrap();

	if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Plugin')) {
		return;
	}

	\WebDigiTech\WooProductEmailer\Plugin::boot();
}

add_action('plugins_loaded', 'wdt_wcpe_load_textdomain', 5);
add_action('plugins_loaded', 'wdt_wcpe_init', 20);

/**
 * Активиране на плъгина.
 *
 * @return void
 */
function wdt_wcpe_activate(): void
{
	wdt_wcpe_bootstrap();

	if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Installer')) {
		deactivate_plugins(WDT_WCPE_BASENAME);

		wp_die(
			esc_html__(
				'WebDigiTech Woo Product Emailer could not be activated because the installer class is missing.',
				'webdigitech-woo-product-emailer'
			)
		);
	}

	\WebDigiTech\WooProductEmailer\Installer::activate();
}

/**
 * Деактивиране на плъгина.
 *
 * @return void
 */
function wdt_wcpe_deactivate(): void
{
	wdt_wcpe_bootstrap();

	if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Installer')) {
		return;
	}

	\WebDigiTech\WooProductEmailer\Installer::deactivate();
}

register_activation_hook(__FILE__, 'wdt_wcpe_activate');
register_deactivation_hook(__FILE__, 'wdt_wcpe_deactivate');