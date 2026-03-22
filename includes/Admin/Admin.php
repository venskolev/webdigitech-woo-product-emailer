<?php
/**
 * Централна администрация на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

use WebDigiTech\WooProductEmailer\API\AjaxTestEmail;
use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;
use WebDigiTech\WooProductEmailer\Product\ProductEmailFields;
use WebDigiTech\WooProductEmailer\Product\ProductEmailMeta;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Регистрира всички admin hooks и вързва backend администрацията на плъгина.
 */
final class Admin
{
	/**
	 * Предпазва от двойна регистрация.
	 */
	private static bool $registered = false;

	/**
	 * Регистрира всички admin hooks.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		if (self::$registered) {
			return;
		}

		add_action('admin_menu', array(__CLASS__, 'register_admin_menu'));
		add_action('admin_init', array(__CLASS__, 'register_admin_runtime'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
		add_filter('admin_footer_text', array(__CLASS__, 'filter_admin_footer_text'));

		if (class_exists(AjaxTestEmail::class) && method_exists(AjaxTestEmail::class, 'register')) {
			AjaxTestEmail::register();

			Logger::log_debug(
				'Admin AJAX test email handler registered.',
				array(
					'action' => Constants::AJAX_ACTION_TEST_EMAIL,
				)
			);
		}

		self::$registered = true;

		Logger::log_debug(
			'Admin hooks registered successfully.',
			array(
				'menu_slugs' => array(
					Constants::MENU_SLUG_SETTINGS,
					Constants::MENU_SLUG_LOGS,
					Constants::MENU_SLUG_TOOLS,
					Constants::MENU_SLUG_SYSTEM_STATUS,
				),
			)
		);
	}

	/**
	 * Регистрира runtime интеграциите, които са само за admin контекст.
	 *
	 * @return void
	 */
	public static function register_admin_runtime(): void
	{
		if (! is_admin()) {
			return;
		}

		ProductEmailFields::register();
		ProductEmailMeta::register();
	}

	/**
	 * Регистрира менюта и подменюта в WordPress администрацията.
	 *
	 * @return void
	 */
	public static function register_admin_menu(): void
	{
		if (! Capabilities::current_user_can_manage()) {
			return;
		}

		$capability = Capabilities::manage_capability();

		add_menu_page(
			esc_html__('Product Emails', 'webdigitech-woo-product-emailer'),
			esc_html__('Product Emails', 'webdigitech-woo-product-emailer'),
			$capability,
			Constants::MENU_SLUG_SETTINGS,
			array(__CLASS__, 'render_settings_page'),
			'dashicons-email-alt2',
			56
		);

		add_submenu_page(
			Constants::MENU_SLUG_SETTINGS,
			esc_html__('Settings', 'webdigitech-woo-product-emailer'),
			esc_html__('Settings', 'webdigitech-woo-product-emailer'),
			$capability,
			Constants::MENU_SLUG_SETTINGS,
			array(__CLASS__, 'render_settings_page')
		);

		add_submenu_page(
			Constants::MENU_SLUG_SETTINGS,
			esc_html__('Logs', 'webdigitech-woo-product-emailer'),
			esc_html__('Logs', 'webdigitech-woo-product-emailer'),
			$capability,
			Constants::MENU_SLUG_LOGS,
			array(__CLASS__, 'render_logs_page')
		);

		add_submenu_page(
			Constants::MENU_SLUG_SETTINGS,
			esc_html__('Tools', 'webdigitech-woo-product-emailer'),
			esc_html__('Tools', 'webdigitech-woo-product-emailer'),
			$capability,
			Constants::MENU_SLUG_TOOLS,
			array(__CLASS__, 'render_tools_page')
		);

		add_submenu_page(
			Constants::MENU_SLUG_SETTINGS,
			esc_html__('System Status', 'webdigitech-woo-product-emailer'),
			esc_html__('System Status', 'webdigitech-woo-product-emailer'),
			$capability,
			Constants::MENU_SLUG_SYSTEM_STATUS,
			array(__CLASS__, 'render_system_status_page')
		);
	}

	/**
	 * Зарежда CSS/JS assets за plugin admin страниците и product edit екрана.
	 *
	 * @param string $hook_suffix Текущият WP admin hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets(string $hook_suffix): void
	{
		$is_plugin_admin_screen = self::is_plugin_admin_screen($hook_suffix);

		Logger::log_debug(
			'Admin enqueue evaluation executed.',
			array(
				'hook_suffix'            => $hook_suffix,
				'is_plugin_admin_screen' => $is_plugin_admin_screen ? 'yes' : 'no',
				'page_param'             => self::current_admin_page_slug(),
				'screen_base'            => self::current_screen_base(),
			)
		);

		if ($is_plugin_admin_screen) {
			Logger::log_debug(
				'Enqueuing plugin admin assets.',
				array(
					'hook_suffix' => $hook_suffix,
					'page_param'  => self::current_admin_page_slug(),
					'screen_base' => self::current_screen_base(),
				)
			);

			self::enqueue_plugin_admin_assets();
		}

		if (self::is_product_edit_screen($hook_suffix)) {
			self::enqueue_product_panel_assets();
		}
	}

	/**
	 * Подменя footer текста само на страниците на плъгина.
	 *
	 * @param string $footer_text Оригинален footer текст.
	 * @return string
	 */
	public static function filter_admin_footer_text(string $footer_text): string
	{
		if (! self::is_plugin_admin_screen()) {
			return $footer_text;
		}

		return sprintf(
			/* translators: %s: plugin version */
			esc_html__('WebDigiTech Woo Product Emailer — version %s', 'webdigitech-woo-product-emailer'),
			esc_html((string) WDT_WCPE_VERSION)
		);
	}

	/**
	 * Рендерира settings страницата.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void
	{
		self::render_page(
			SettingsPage::class,
			'render',
			esc_html__('Settings', 'webdigitech-woo-product-emailer'),
			esc_html__('Global plugin settings and fallback email template will appear here.', 'webdigitech-woo-product-emailer')
		);
	}

	/**
	 * Рендерира logs страницата.
	 *
	 * @return void
	 */
	public static function render_logs_page(): void
	{
		self::render_page(
			LogsPage::class,
			'render',
			esc_html__('Logs', 'webdigitech-woo-product-emailer'),
			esc_html__('Dispatch logs, filtering and pagination will appear here.', 'webdigitech-woo-product-emailer')
		);
	}

	/**
	 * Рендерира tools страницата.
	 *
	 * @return void
	 */
	public static function render_tools_page(): void
	{
		self::render_page(
			ToolsPage::class,
			'render',
			esc_html__('Tools', 'webdigitech-woo-product-emailer'),
			esc_html__('Manual test email, maintenance actions and diagnostics tools will appear here.', 'webdigitech-woo-product-emailer')
		);
	}

	/**
	 * Рендерира system status страницата.
	 *
	 * @return void
	 */
	public static function render_system_status_page(): void
	{
		self::render_page(
			SystemStatusPage::class,
			'render',
			esc_html__('System Status', 'webdigitech-woo-product-emailer'),
			esc_html__('Runtime checks, cron status and environment diagnostics will appear here.', 'webdigitech-woo-product-emailer')
		);
	}

	/**
	 * Рендерира admin страница или fallback shell, ако конкретният page class още липсва.
	 *
	 * @param class-string $class_name Име на класа на страницата.
	 * @param string       $method Име на render метода.
	 * @param string       $title Заглавие на fallback shell-а.
	 * @param string       $description Описание на fallback shell-а.
	 * @return void
	 */
	private static function render_page(string $class_name, string $method, string $title, string $description): void
	{
		Capabilities::enforce_manage_capability();

		if (class_exists($class_name) && method_exists($class_name, $method)) {
			$class_name::$method();
			return;
		}

		self::render_page_shell($title, $description);
	}

	/**
	 * Рендерира минимален admin shell, когато страницата още не е имплементирана.
	 *
	 * @param string $title Заглавие.
	 * @param string $description Описание.
	 * @return void
	 */
	private static function render_page_shell(string $title, string $description): void
	{
		$settings = Helpers::get_settings();

		echo '<div class="wrap wdt-wcpe-admin-wrap">';
		echo '<h1>' . esc_html($title) . '</h1>';
		echo '<div class="notice notice-info"><p>' . esc_html($description) . '</p></div>';
		echo '<div class="card" style="max-width: 960px; padding: 16px 20px;">';
		echo '<h2>' . esc_html__('Current plugin snapshot', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p>' . esc_html__('This page is already connected to the plugin runtime. The dedicated UI for this section is the next implementation step.', 'webdigitech-woo-product-emailer') . '</p>';
		echo '<ul style="list-style: disc; padding-left: 20px;">';
		echo '<li>' . esc_html__('Plugin enabled:', 'webdigitech-woo-product-emailer') . ' <strong>' . esc_html((string) $settings['plugin_enabled']) . '</strong></li>';
		echo '<li>' . esc_html__('Fallback email enabled:', 'webdigitech-woo-product-emailer') . ' <strong>' . esc_html((string) $settings['fallback_email_enabled']) . '</strong></li>';
		echo '<li>' . esc_html__('Logging enabled:', 'webdigitech-woo-product-emailer') . ' <strong>' . esc_html((string) $settings['enable_logging']) . '</strong></li>';
		echo '<li>' . esc_html__('Retry enabled:', 'webdigitech-woo-product-emailer') . ' <strong>' . esc_html((string) $settings['retry_failed_sends']) . '</strong></li>';
		echo '<li>' . esc_html__('Recovery enabled:', 'webdigitech-woo-product-emailer') . ' <strong>' . esc_html((string) $settings['recovery_enabled']) . '</strong></li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Зарежда admin CSS/JS за plugin страниците.
	 *
	 * @return void
	 */
	private static function enqueue_plugin_admin_assets(): void
	{
		$style_path  = WDT_WCPE_DIR . 'assets/css/admin.css';
		$script_path = WDT_WCPE_DIR . 'assets/js/admin.js';

		if (is_readable($style_path)) {
			wp_enqueue_style(
				'wdt-wcpe-admin',
				WDT_WCPE_URL . 'assets/css/admin.css',
				array(),
				self::asset_version($style_path)
			);
		} else {
			Logger::log_error(
				'Admin stylesheet could not be enqueued because the file is missing or unreadable.',
				array(
					'style_path' => $style_path,
				)
			);
		}

		if (is_readable($script_path)) {
			wp_enqueue_script(
				'wdt-wcpe-admin',
				WDT_WCPE_URL . 'assets/js/admin.js',
				array('jquery'),
				self::asset_version($script_path),
				true
			);

			wp_localize_script(
				'wdt-wcpe-admin',
				'wdtWcpeAdmin',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce(Constants::AJAX_ACTION_TEST_EMAIL),
					'pages'   => array(
						'settings'     => Helpers::admin_page_url(Constants::MENU_SLUG_SETTINGS),
						'logs'         => Helpers::admin_page_url(Constants::MENU_SLUG_LOGS),
						'tools'        => Helpers::admin_page_url(Constants::MENU_SLUG_TOOLS),
						'systemStatus' => Helpers::admin_page_url(Constants::MENU_SLUG_SYSTEM_STATUS),
					),
					'i18n'    => array(
						'testEmailAction' => Constants::AJAX_ACTION_TEST_EMAIL,
						'genericError'    => esc_html__('Something went wrong. Please try again.', 'webdigitech-woo-product-emailer'),
					),
				)
			);

			Logger::log_debug(
				'Admin script localized successfully.',
				array(
					'handle'   => 'wdt-wcpe-admin',
					'ajax_url' => admin_url('admin-ajax.php'),
					'action'   => Constants::AJAX_ACTION_TEST_EMAIL,
				)
			);
		} else {
			Logger::log_error(
				'Admin script could not be enqueued because the file is missing or unreadable.',
				array(
					'script_path' => $script_path,
				)
			);
		}
	}

	/**
	 * Зарежда assets за WooCommerce product editor интеграцията.
	 *
	 * @return void
	 */
	private static function enqueue_product_panel_assets(): void
	{
		$style_path  = WDT_WCPE_DIR . 'assets/css/product-panel.css';
		$script_path = WDT_WCPE_DIR . 'assets/js/product-panel.js';

		if (is_readable($style_path)) {
			wp_enqueue_style(
				'wdt-wcpe-product-panel',
				WDT_WCPE_URL . 'assets/css/product-panel.css',
				array(),
				self::asset_version($style_path)
			);
		}

		if (is_readable($script_path)) {
			wp_enqueue_script(
				'wdt-wcpe-product-panel',
				WDT_WCPE_URL . 'assets/js/product-panel.js',
				array('jquery'),
				self::asset_version($script_path),
				true
			);
		}
	}

	/**
	 * Проверява дали текущият екран е admin страница на плъгина.
	 *
	 * @param string|null $hook_suffix Hook suffix от admin_enqueue_scripts.
	 * @return bool
	 */
	private static function is_plugin_admin_screen(?string $hook_suffix = null): bool
	{
		$current_page = self::current_admin_page_slug();

		if ($current_page !== '' && in_array($current_page, self::plugin_admin_page_slugs(), true)) {
			return true;
		}

		if (is_string($hook_suffix) && $hook_suffix !== '' && in_array($hook_suffix, self::plugin_admin_hook_suffixes(), true)) {
			return true;
		}

		if (! function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();

		if (! $screen instanceof \WP_Screen) {
			return false;
		}

		$screen_base = (string) $screen->base;
		$screen_id   = (string) $screen->id;

		if (in_array($screen_base, self::plugin_admin_hook_suffixes(), true)) {
			return true;
		}

		if (in_array($screen_id, self::plugin_admin_hook_suffixes(), true)) {
			return true;
		}

		foreach (self::plugin_admin_page_slugs() as $slug) {
			if ($screen_base !== '' && str_contains($screen_base, $slug)) {
				return true;
			}

			if ($screen_id !== '' && str_contains($screen_id, $slug)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Проверява дали текущият екран е edit/new product screen.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	private static function is_product_edit_screen(string $hook_suffix): bool
	{
		if (! in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return false;
		}

		if (! function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();

		return $screen instanceof \WP_Screen && $screen->post_type === 'product';
	}

	/**
	 * Hook suffix-и за plugin admin страниците.
	 *
	 * @return string[]
	 */
	private static function plugin_admin_hook_suffixes(): array
	{
		return array(
			'toplevel_page_' . Constants::MENU_SLUG_SETTINGS,
			Constants::MENU_SLUG_SETTINGS . '_page_' . Constants::MENU_SLUG_LOGS,
			Constants::MENU_SLUG_SETTINGS . '_page_' . Constants::MENU_SLUG_TOOLS,
			Constants::MENU_SLUG_SETTINGS . '_page_' . Constants::MENU_SLUG_SYSTEM_STATUS,
		);
	}

	/**
	 * Позволените page slug стойности за администрацията на плъгина.
	 *
	 * @return string[]
	 */
	private static function plugin_admin_page_slugs(): array
	{
		return array(
			Constants::MENU_SLUG_SETTINGS,
			Constants::MENU_SLUG_LOGS,
			Constants::MENU_SLUG_TOOLS,
			Constants::MENU_SLUG_SYSTEM_STATUS,
		);
	}

	/**
	 * Връща текущия admin page slug от query string.
	 *
	 * @return string
	 */
	private static function current_admin_page_slug(): string
	{
		if (! isset($_GET['page'])) {
			return '';
		}

		return Helpers::sanitize_text(wp_unslash($_GET['page']));
	}

	/**
	 * Връща current screen base за debug.
	 *
	 * @return string
	 */
	private static function current_screen_base(): string
	{
		if (! function_exists('get_current_screen')) {
			return '';
		}

		$screen = get_current_screen();

		if (! $screen instanceof \WP_Screen) {
			return '';
		}

		return (string) $screen->base;
	}

	/**
	 * Изчислява версия на asset по filemtime.
	 *
	 * @param string $file_path Път до файла.
	 * @return string
	 */
	private static function asset_version(string $file_path): string
	{
		$filemtime = @filemtime($file_path);

		if ($filemtime === false) {
			return (string) WDT_WCPE_VERSION;
		}

		return (string) $filemtime;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}