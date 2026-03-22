<?php
/**
 * Uninstall bootstrap file for WebDigiTech Woo Product Emailer.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

if (! defined('WDT_WCPE_FILE')) {
	define('WDT_WCPE_FILE', __FILE__);
}

if (! defined('WDT_WCPE_BASENAME')) {
	define('WDT_WCPE_BASENAME', plugin_basename(__FILE__));
}

if (! defined('WDT_WCPE_DIR')) {
	define('WDT_WCPE_DIR', plugin_dir_path(__FILE__));
}

if (! defined('WDT_WCPE_URL')) {
	define('WDT_WCPE_URL', plugin_dir_url(__FILE__));
}

if (! defined('WDT_WCPE_VERSION')) {
	define('WDT_WCPE_VERSION', '1.0.0');
}

if (! defined('WDT_WCPE_TEXT_DOMAIN')) {
	define('WDT_WCPE_TEXT_DOMAIN', 'webdigitech-woo-product-emailer');
}

$required_files = array(
	WDT_WCPE_DIR . 'includes/Constants.php',
	WDT_WCPE_DIR . 'includes/Autoloader.php',
	WDT_WCPE_DIR . 'includes/Dependencies.php',
	WDT_WCPE_DIR . 'includes/Database/Schema.php',
	WDT_WCPE_DIR . 'includes/Uninstaller.php',
);

foreach ($required_files as $required_file) {
	if (is_readable($required_file)) {
		require_once $required_file;
	}
}

if (class_exists('\\WebDigiTech\\WooProductEmailer\\Uninstaller')) {
	\WebDigiTech\WooProductEmailer\Uninstaller::uninstall();
}