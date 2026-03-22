<?php
/**
 * Главен orchestrator на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

use WebDigiTech\WooProductEmailer\Admin\Admin;
use WebDigiTech\WooProductEmailer\Database\Migrations;
use WebDigiTech\WooProductEmailer\Woo\Hooks;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Главен клас за инициализация на всички модули.
 */
final class Plugin
{
	/**
	 * Дали плъгинът вече е boot-нат.
	 */
	private static bool $booted = false;

	/**
	 * Стартира плъгина.
	 *
	 * @return void
	 */
	public static function boot(): void
	{
		if (self::$booted) {
			return;
		}

		if (! Dependencies::can_boot()) {
			add_action('admin_notices', array(Dependencies::class, 'maybe_render_admin_notice'));
			return;
		}

		self::load_runtime_files();
		self::maybe_upgrade_database();
		self::register_hooks();

		self::$booted = true;
	}

	/**
	 * Зарежда runtime файловете, които не се autoload-ват предварително.
	 *
	 * @return void
	 */
	private static function load_runtime_files(): void
	{
		$required_files = array(
			WDT_WCPE_DIR . 'includes/Database/Schema.php',
			WDT_WCPE_DIR . 'includes/Database/Migrations.php',
			WDT_WCPE_DIR . 'includes/Admin/Admin.php',
			WDT_WCPE_DIR . 'includes/Woo/Hooks.php',
			WDT_WCPE_DIR . 'includes/Cron.php',
		);

		foreach ($required_files as $required_file) {
			if (is_readable($required_file)) {
				require_once $required_file;
			}
		}
	}

	/**
	 * Прави DB upgrade при разминаване на schema version.
	 *
	 * @return void
	 */
	private static function maybe_upgrade_database(): void
	{
		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Migrations')) {
			return;
		}

		Migrations::maybe_migrate();
	}

	/**
	 * Регистрира всички runtime hooks.
	 *
	 * @return void
	 */
	private static function register_hooks(): void
	{
		self::register_general_hooks();
		self::register_admin_hooks();
		self::register_woo_hooks();
		self::register_cron_hooks();
	}

	/**
	 * Регистрира общи hooks.
	 *
	 * @return void
	 */
	private static function register_general_hooks(): void
	{
		add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedules'));
		add_filter('plugin_action_links_' . WDT_WCPE_BASENAME, array(__CLASS__, 'add_plugin_action_links'));
	}

	/**
	 * Регистрира admin hooks.
	 *
	 * @return void
	 */
	private static function register_admin_hooks(): void
	{
		if (! is_admin()) {
			return;
		}

		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Admin\\Admin')) {
			Admin::register();
		}
	}

	/**
	 * Регистрира WooCommerce hooks.
	 *
	 * @return void
	 */
	private static function register_woo_hooks(): void
	{
		if (! class_exists('\\WooCommerce')) {
			return;
		}

		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Woo\\Hooks')) {
			Hooks::register();
		}
	}

	/**
	 * Регистрира cron hooks.
	 *
	 * @return void
	 */
	private static function register_cron_hooks(): void
	{
		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Cron')) {
			return;
		}

		Cron::register();
	}

	/**
	 * Добавя custom cron schedules.
	 *
	 * @param array<string, array<string, int|string>> $schedules Съществуващите schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function register_cron_schedules(array $schedules): array
	{
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
	}

	/**
	 * Добавя action links в списъка с плъгини.
	 *
	 * @param string[] $links Съществуващите линкове.
	 * @return string[]
	 */
	public static function add_plugin_action_links(array $links): array
	{
		$settings_url = admin_url('admin.php?page=' . Constants::MENU_SLUG_SETTINGS);
		$tools_url = admin_url('admin.php?page=' . Constants::MENU_SLUG_TOOLS);

		$custom_links = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url($settings_url),
				esc_html__('Settings', 'webdigitech-woo-product-emailer')
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url($tools_url),
				esc_html__('Tools', 'webdigitech-woo-product-emailer')
			),
		);

		return array_merge($custom_links, $links);
	}

	/**
	 * Проверява дали плъгинът е boot-нат.
	 *
	 * @return bool
	 */
	public static function is_booted(): bool
	{
		return self::$booted;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}