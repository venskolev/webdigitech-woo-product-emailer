<?php
/**
 * Логика за uninstall почистване на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

use WebDigiTech\WooProductEmailer\Database\Schema;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за пълното премахване на данните на плъгина.
 */
final class Uninstaller
{
	/**
	 * Изпълнява uninstall логиката.
	 *
	 * @return void
	 */
	public static function uninstall(): void
	{
		if (! self::can_run()) {
			return;
		}

		self::unschedule_cron_jobs();

		if (! self::should_preserve_data()) {
			self::delete_plugin_options();
			self::delete_product_meta();
			self::drop_database_tables();
		}
	}

	/**
	 * Проверява дали uninstall може да бъде изпълнен безопасно.
	 *
	 * @return bool
	 */
	private static function can_run(): bool
	{
		return defined('WP_UNINSTALL_PLUGIN');
	}

	/**
	 * Проверява дали данните трябва да се запазят.
	 *
	 * @return bool
	 */
	private static function should_preserve_data(): bool
	{
		$preserve_option = get_option(Constants::OPTION_PRESERVE_DATA_ON_UNINSTALL, 'yes');

		return is_string($preserve_option) && $preserve_option === 'yes';
	}

	/**
	 * Премахва всички cron задачи на плъгина.
	 *
	 * @return void
	 */
	private static function unschedule_cron_jobs(): void
	{
		self::clear_scheduled_hook(Constants::CRON_HOOK_RETRY_FAILED_EMAILS);
		self::clear_scheduled_hook(Constants::CRON_HOOK_RECOVERY_SCAN);
	}

	/**
	 * Изтрива всички опции на плъгина.
	 *
	 * @return void
	 */
	private static function delete_plugin_options(): void
	{
		$options = array(
			Constants::OPTION_DB_VERSION,
			Constants::OPTION_SETTINGS,
			Constants::OPTION_PRESERVE_DATA_ON_UNINSTALL,
			Constants::OPTION_LAST_RECOVERY_RUN,
			Constants::OPTION_LAST_RETRY_RUN,
			Constants::OPTION_LAST_TEST_EMAIL_SENT_AT,
			Constants::OPTION_LAST_TEST_EMAIL_ERROR,
		);

		foreach ($options as $option_name) {
			delete_option($option_name);
		}
	}

	/**
	 * Изтрива product meta keys, използвани от плъгина.
	 *
	 * @return void
	 */
	private static function delete_product_meta(): void
	{
		global $wpdb;

		$meta_keys = array(
			Constants::META_ENABLE_CUSTOM_EMAIL,
			Constants::META_PRODUCT_EMAIL_ENABLED,
			Constants::META_PRODUCT_EMAIL_SUBJECT,
			Constants::META_PRODUCT_EMAIL_HEADING,
			Constants::META_PRODUCT_EMAIL_BODY_HTML,
			Constants::META_PRODUCT_EMAIL_BODY_TEXT,
			Constants::META_PRODUCT_EMAIL_NOTES,
		);

		foreach ($meta_keys as $meta_key) {
			$wpdb->delete(
				$wpdb->postmeta,
				array(
					'meta_key' => $meta_key,
				),
				array('%s')
			);
		}
	}

	/**
	 * Изтрива custom DB таблиците на плъгина.
	 *
	 * @return void
	 */
	private static function drop_database_tables(): void
	{
		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Schema')) {
			$schema_file = WDT_WCPE_DIR . 'includes/Database/Schema.php';

			if (is_readable($schema_file)) {
				require_once $schema_file;
			}
		}

		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Schema')) {
			Schema::drop_tables();
		}
	}

	/**
	 * Премахва всички планирани изпълнения за конкретен hook.
	 *
	 * @param string $hook Hook името.
	 * @return void
	 */
	private static function clear_scheduled_hook(string $hook): void
	{
		$timestamp = wp_next_scheduled($hook);

		while ($timestamp !== false) {
			wp_unschedule_event($timestamp, $hook);
			$timestamp = wp_next_scheduled($hook);
		}

		if (function_exists('wp_clear_scheduled_hook')) {
			wp_clear_scheduled_hook($hook);
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}