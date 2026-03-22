<?php
/**
 * Проверка на зависимостите на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за валидирането на средата и зависимостите.
 */
final class Dependencies
{
	/**
	 * Минимална версия на PHP.
	 */
	private const MIN_PHP_VERSION = '8.0';

	/**
	 * Минимална версия на WordPress.
	 */
	private const MIN_WP_VERSION = '6.6';

	/**
	 * Минимална версия на WooCommerce.
	 */
	private const MIN_WC_VERSION = '8.5';

	/**
	 * Проверява дали всички зависимости са налични.
	 *
	 * @return bool
	 */
	public static function are_satisfied(): bool
	{
		return self::php_version_is_supported()
			&& self::wordpress_version_is_supported()
			&& self::woocommerce_is_active()
			&& self::woocommerce_version_is_supported();
	}

	/**
	 * Връща списък с всички открити проблеми.
	 *
	 * @return string[]
	 */
	public static function get_issues(): array
	{
		$issues = array();

		if (! self::php_version_is_supported()) {
			$issues[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__('WebDigiTech Woo Product Emailer requires PHP %1$s or newer. Current version: %2$s.', 'webdigitech-woo-product-emailer'),
				self::MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		if (! self::wordpress_version_is_supported()) {
			$issues[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version */
				__('WebDigiTech Woo Product Emailer requires WordPress %1$s or newer. Current version: %2$s.', 'webdigitech-woo-product-emailer'),
				self::MIN_WP_VERSION,
				self::get_wordpress_version()
			);
		}

		if (! self::woocommerce_is_active()) {
			$issues[] = __(
				'WebDigiTech Woo Product Emailer requires WooCommerce to be installed and active.',
				'webdigitech-woo-product-emailer'
			);
		} elseif (! self::woocommerce_version_is_supported()) {
			$issues[] = sprintf(
				/* translators: 1: required WooCommerce version, 2: current WooCommerce version */
				__('WebDigiTech Woo Product Emailer requires WooCommerce %1$s or newer. Current version: %2$s.', 'webdigitech-woo-product-emailer'),
				self::MIN_WC_VERSION,
				self::get_woocommerce_version()
			);
		}

		if (! self::required_functions_exist()) {
			$issues[] = __(
				'WebDigiTech Woo Product Emailer cannot start because one or more required WordPress or WooCommerce functions are unavailable.',
				'webdigitech-woo-product-emailer'
			);
		}

		return $issues;
	}

	/**
	 * Показва admin notices при проблеми.
	 *
	 * @return void
	 */
	public static function maybe_render_admin_notice(): void
	{
		$issues = self::get_issues();

		if ($issues === array()) {
			return;
		}

		if (! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html__('WebDigiTech Woo Product Emailer', 'webdigitech-woo-product-emailer') . '</strong></p>';
		echo '<ul style="margin: 0 0 0 18px; list-style: disc;">';

		foreach ($issues as $issue) {
			echo '<li>' . esc_html($issue) . '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Проверява версията на PHP.
	 *
	 * @return bool
	 */
	public static function php_version_is_supported(): bool
	{
		return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
	}

	/**
	 * Проверява версията на WordPress.
	 *
	 * @return bool
	 */
	public static function wordpress_version_is_supported(): bool
	{
		return version_compare(self::get_wordpress_version(), self::MIN_WP_VERSION, '>=');
	}

	/**
	 * Проверява дали WooCommerce е активен.
	 *
	 * @return bool
	 */
	public static function woocommerce_is_active(): bool
	{
		return class_exists('WooCommerce')
			&& function_exists('WC')
			&& function_exists('wc_get_order');
	}

	/**
	 * Проверява версията на WooCommerce.
	 *
	 * @return bool
	 */
	public static function woocommerce_version_is_supported(): bool
	{
		$wc_version = self::get_woocommerce_version();

		if ($wc_version === '') {
			return false;
		}

		return version_compare($wc_version, self::MIN_WC_VERSION, '>=');
	}

	/**
	 * Проверява наличието на критични функции.
	 *
	 * @return bool
	 */
	public static function required_functions_exist(): bool
	{
		$required_functions = array(
			'add_action',
			'add_filter',
			'register_activation_hook',
			'register_deactivation_hook',
			'wp_mail',
			'get_option',
			'update_option',
			'delete_option',
			'sanitize_text_field',
			'is_email',
			'wc_get_order',
		);

		foreach ($required_functions as $function_name) {
			if (! function_exists($function_name)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Връща версията на WordPress.
	 *
	 * @return string
	 */
	public static function get_wordpress_version(): string
	{
		global $wp_version;

		return is_string($wp_version) ? $wp_version : '';
	}

	/**
	 * Връща версията на WooCommerce.
	 *
	 * @return string
	 */
	public static function get_woocommerce_version(): string
	{
		if (defined('WC_VERSION') && is_string(WC_VERSION)) {
			return WC_VERSION;
		}

		if (function_exists('WC')) {
			$woocommerce = WC();

			if (is_object($woocommerce) && isset($woocommerce->version) && is_string($woocommerce->version)) {
				return $woocommerce->version;
			}
		}

		return '';
	}

	/**
	 * Проверява дали плъгинът може да стартира напълно.
	 *
	 * @return bool
	 */
	public static function can_boot(): bool
	{
		return self::are_satisfied();
	}

	/**
	 * Проверява дали плъгинът може да бъде активиран.
	 *
	 * @return bool
	 */
	public static function can_activate(): bool
	{
		return self::are_satisfied();
	}

	/**
	 * Спира активацията и показва всички проблеми.
	 *
	 * @return void
	 */
	public static function fail_activation_if_needed(): void
	{
		$issues = self::get_issues();

		if ($issues === array()) {
			return;
		}

		deactivate_plugins(WDT_WCPE_BASENAME);

		$message  = '<h1>' . esc_html__('Plugin activation failed', 'webdigitech-woo-product-emailer') . '</h1>';
		$message .= '<p>' . esc_html__('WebDigiTech Woo Product Emailer could not be activated because the environment requirements are not satisfied.', 'webdigitech-woo-product-emailer') . '</p>';
		$message .= '<ul>';

		foreach ($issues as $issue) {
			$message .= '<li>' . esc_html($issue) . '</li>';
		}

		$message .= '</ul>';
		$message .= '<p><a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Return to Plugins page', 'webdigitech-woo-product-emailer') . '</a></p>';

		wp_die(
			$message,
			esc_html__('Activation error', 'webdigitech-woo-product-emailer'),
			array(
				'response'  => 200,
				'back_link' => false,
			)
		);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}