<?php
/**
 * Инсталация, деактивация и начална подготовка на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

use WebDigiTech\WooProductEmailer\Database\Migrations;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за activate/deactivate lifecycle на плъгина.
 */
final class Installer
{
	/**
	 * Активира плъгина.
	 *
	 * @return void
	 */
	public static function activate(): void
	{
		Dependencies::fail_activation_if_needed();

		self::create_or_update_database();
		self::create_default_options();
		self::schedule_cron_jobs();
		self::flush_rewrite_rules_safely();
	}

	/**
	 * Деактивира плъгина.
	 *
	 * @return void
	 */
	public static function deactivate(): void
	{
		self::unschedule_cron_jobs();
		self::flush_rewrite_rules_safely();
	}

	/**
	 * Създава или обновява DB структурата.
	 *
	 * @return void
	 */
	private static function create_or_update_database(): void
	{
		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Migrations')) {
			$migrations_file = WDT_WCPE_DIR . 'includes/Database/Migrations.php';

			if (is_readable($migrations_file)) {
				require_once $migrations_file;
			}
		}

		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Migrations')) {
			wp_die(
				esc_html__(
					'WebDigiTech Woo Product Emailer could not run the database migrations because the Migrations class is missing.',
					'webdigitech-woo-product-emailer'
				)
			);
		}

		$result = Migrations::force_migrate();

		if (! $result) {
			wp_die(
				esc_html__(
					'WebDigiTech Woo Product Emailer could not complete the database migration process.',
					'webdigitech-woo-product-emailer'
				)
			);
		}
	}

	/**
	 * Създава началните опции, ако липсват.
	 *
	 * @return void
	 */
	private static function create_default_options(): void
	{
		$default_settings  = Constants::default_settings();
		$existing_settings = get_option(Constants::OPTION_SETTINGS, null);

		if (! is_array($existing_settings)) {
			add_option(Constants::OPTION_SETTINGS, $default_settings, '', false);
		} else {
			$merged_settings = wp_parse_args($existing_settings, $default_settings);
			update_option(Constants::OPTION_SETTINGS, $merged_settings, false);
		}

		if (get_option(Constants::OPTION_PRESERVE_DATA_ON_UNINSTALL, null) === null) {
			add_option(
				Constants::OPTION_PRESERVE_DATA_ON_UNINSTALL,
				$default_settings['preserve_data_on_uninstall'],
				'',
				false
			);
		}

		if (get_option(Constants::OPTION_LAST_RECOVERY_RUN, null) === null) {
			add_option(Constants::OPTION_LAST_RECOVERY_RUN, '', '', false);
		}

		if (get_option(Constants::OPTION_LAST_RETRY_RUN, null) === null) {
			add_option(Constants::OPTION_LAST_RETRY_RUN, '', '', false);
		}

		if (get_option(Constants::OPTION_LAST_CLEANUP_RUN, null) === null) {
			add_option(Constants::OPTION_LAST_CLEANUP_RUN, '', '', false);
		}

		if (get_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, null) === null) {
			add_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, '', '', false);
		}

		if (get_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, null) === null) {
			add_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, '', '', false);
		}
	}

	/**
	 * Регистрира custom cron интервали и планира задачите.
	 *
	 * @return void
	 */
	private static function schedule_cron_jobs(): void
	{
		self::register_cron_schedule_filter();

		if (! wp_next_scheduled(Constants::CRON_HOOK_RETRY_FAILED_EMAILS)) {
			wp_schedule_event(
				time() + MINUTE_IN_SECONDS,
				Constants::CRON_SCHEDULE_EVERY_FIFTEEN_MINUTES,
				Constants::CRON_HOOK_RETRY_FAILED_EMAILS
			);
		}

		if (! wp_next_scheduled(Constants::CRON_HOOK_RECOVERY_SCAN)) {
			wp_schedule_event(
				time() + (2 * MINUTE_IN_SECONDS),
				Constants::CRON_SCHEDULE_EVERY_FIFTEEN_MINUTES,
				Constants::CRON_HOOK_RECOVERY_SCAN
			);
		}

		if (! wp_next_scheduled(Constants::CRON_HOOK_CLEANUP_LOGS)) {
			wp_schedule_event(
				time() + (3 * MINUTE_IN_SECONDS),
				'daily',
				Constants::CRON_HOOK_CLEANUP_LOGS
			);
		}
	}

	/**
	 * Премахва cron задачите при деактивация.
	 *
	 * @return void
	 */
	private static function unschedule_cron_jobs(): void
	{
		self::clear_scheduled_hook(Constants::CRON_HOOK_RETRY_FAILED_EMAILS);
		self::clear_scheduled_hook(Constants::CRON_HOOK_RECOVERY_SCAN);
		self::clear_scheduled_hook(Constants::CRON_HOOK_CLEANUP_LOGS);
	}

	/**
	 * Разчиства всички планирани изпълнения за конкретен hook.
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
	 * Регистрира filter за custom cron интервал.
	 *
	 * @return void
	 */
	private static function register_cron_schedule_filter(): void
	{
		$callback = static function (array $schedules): array {
			if (! isset($schedules[Constants::CRON_SCHEDULE_EVERY_FIFTEEN_MINUTES])) {
				$schedules[Constants::CRON_SCHEDULE_EVERY_FIFTEEN_MINUTES] = array(
					'interval' => Constants::DEFAULT_RETRY_INTERVAL_MINUTES * MINUTE_IN_SECONDS,
					'display'  => esc_html__(
						'Every 15 Minutes (WebDigiTech Woo Product Emailer)',
						'webdigitech-woo-product-emailer'
					),
				);
			}

			return $schedules;
		};

		add_filter('cron_schedules', $callback);
	}

	/**
	 * Безопасно flush-ва rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules_safely(): void
	{
		if (function_exists('flush_rewrite_rules')) {
			flush_rewrite_rules(false);
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}