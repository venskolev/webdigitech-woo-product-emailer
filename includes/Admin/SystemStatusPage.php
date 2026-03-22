<?php
/**
 * System Status страница на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Рендерира system status страницата.
 */
final class SystemStatusPage
{
	/**
	 * Рендерира страницата.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		Capabilities::enforce_manage_capability();

		$settings       = Helpers::get_settings();
		$environment    = self::collect_environment_data();
		$plugin_status  = self::collect_plugin_status($settings);
		$cron_status    = self::collect_cron_status();
		$storage_status = self::collect_storage_status();

		echo '<div class="wrap wdt-wcpe-admin-wrap">';
		echo '<h1>' . esc_html__('System Status', 'webdigitech-woo-product-emailer') . '</h1>';
		echo '<p>' . esc_html__('Review the current runtime environment, plugin readiness, WordPress and WooCommerce integration state, cron scheduling and storage health.', 'webdigitech-woo-product-emailer') . '</p>';

		self::render_health_cards($environment, $plugin_status, $cron_status, $storage_status);
		self::render_environment_section($environment);
		self::render_plugin_section($plugin_status);
		self::render_cron_section($cron_status);
		self::render_storage_section($storage_status);

		FooterRenderer::render();

		echo '</div>';
	}

	/**
	 * Събира environment информация.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_environment_data(): array
	{
		global $wpdb;

		$php_version        = PHP_VERSION;
		$wp_version         = get_bloginfo('version');
		$wc_version         = defined('WC_VERSION') ? (string) WC_VERSION : '';
		$mysql_version      = is_object($wpdb) && method_exists($wpdb, 'db_version')
			? (string) $wpdb->db_version()
			: '';
		$memory_limit       = (string) ini_get('memory_limit');
		$max_execution_time = (string) ini_get('max_execution_time');
		$debug_mode         = defined('WP_DEBUG') && WP_DEBUG;
		$wp_cron_disabled   = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
		$site_url           = home_url('/');
		$admin_email        = (string) get_option('admin_email', '');

		return array(
			'php_version'        => $php_version,
			'wp_version'         => $wp_version,
			'wc_version'         => $wc_version !== '' ? $wc_version : __('Not detected', 'webdigitech-woo-product-emailer'),
			'mysql_version'      => $mysql_version !== '' ? $mysql_version : __('Unknown', 'webdigitech-woo-product-emailer'),
			'memory_limit'       => $memory_limit !== '' ? $memory_limit : __('Unknown', 'webdigitech-woo-product-emailer'),
			'max_execution_time' => $max_execution_time !== '' ? $max_execution_time : __('Unknown', 'webdigitech-woo-product-emailer'),
			'wp_debug'           => $debug_mode,
			'wp_cron_disabled'   => $wp_cron_disabled,
			'site_url'           => $site_url,
			'admin_email'        => $admin_email !== '' ? $admin_email : __('Not configured', 'webdigitech-woo-product-emailer'),
			'is_ssl'             => is_ssl(),
		);
	}

	/**
	 * Събира plugin readiness информация.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return array<string, mixed>
	 */
	private static function collect_plugin_status(array $settings): array
	{
		$logs_table_name      = self::get_logs_table_name();
		$logs_table_exists    = self::table_exists($logs_table_name);
		$fallback_subject     = (string) Helpers::array_get($settings, 'fallback_email_subject', '');
		$fallback_html        = (string) Helpers::array_get($settings, 'fallback_email_body_html', '');
		$fallback_text        = (string) Helpers::array_get($settings, 'fallback_email_body_text', '');
		$test_email_recipient = (string) Helpers::array_get($settings, 'test_email_recipient', '');

		return array(
			'plugin_enabled'           => Helpers::is_yes(Helpers::array_get($settings, 'plugin_enabled', 'yes')),
			'fallback_enabled'         => Helpers::is_yes(Helpers::array_get($settings, 'fallback_email_enabled', 'yes')),
			'fallback_template_valid'  => Helpers::has_valid_fallback_template(),
			'logging_enabled'          => Helpers::is_yes(Helpers::array_get($settings, 'enable_logging', 'yes')),
			'retry_enabled'            => Helpers::is_yes(Helpers::array_get($settings, 'retry_failed_sends', 'yes')),
			'recovery_enabled'         => Helpers::is_yes(Helpers::array_get($settings, 'recovery_enabled', 'yes')),
			'debug_mode'               => Helpers::is_yes(Helpers::array_get($settings, 'debug_mode', 'no')),
			'preserve_on_uninstall'    => Helpers::is_yes(Helpers::array_get($settings, 'preserve_data_on_uninstall', 'yes')),
			'logs_table_exists'        => $logs_table_exists,
			'fallback_subject_present' => $fallback_subject !== '',
			'fallback_html_present'    => $fallback_html !== '',
			'fallback_text_present'    => $fallback_text !== '',
			'test_email_recipient_set' => $test_email_recipient !== '',
			'mail_content_type_mode'   => (string) Helpers::array_get($settings, 'mail_content_type_mode', Constants::DEFAULT_MAIL_CONTENT_TYPE),
			'allowed_order_statuses'   => Helpers::get_allowed_order_statuses(),
			'sender_name_override'     => (string) Helpers::array_get($settings, 'sender_name_override', ''),
			'sender_email_override'    => (string) Helpers::array_get($settings, 'sender_email_override', ''),
			'last_test_email_sent_at'  => (string) get_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, ''),
			'last_test_email_error'    => (string) get_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, ''),
		);
	}

	/**
	 * Събира cron статус.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_cron_status(): array
	{
		$retry_hook       = Constants::CRON_HOOK_RETRY_FAILED_EMAILS;
		$recovery_hook    = Constants::CRON_HOOK_RECOVERY_SCAN;
		$cleanup_hook     = Constants::CRON_HOOK_CLEANUP_LOGS;
		$retry_timestamp   = wp_next_scheduled($retry_hook);
		$recovery_timestamp = wp_next_scheduled($recovery_hook);
		$cleanup_timestamp  = wp_next_scheduled($cleanup_hook);

		return array(
			'retry_hook'         => $retry_hook,
			'recovery_hook'      => $recovery_hook,
			'cleanup_hook'       => $cleanup_hook,
			'retry_scheduled'    => $retry_timestamp !== false,
			'recovery_scheduled' => $recovery_timestamp !== false,
			'cleanup_scheduled'  => $cleanup_timestamp !== false,
			'retry_next_run'     => $retry_timestamp !== false ? wp_date('Y-m-d H:i:s', (int) $retry_timestamp) : __('Not scheduled', 'webdigitech-woo-product-emailer'),
			'recovery_next_run'  => $recovery_timestamp !== false ? wp_date('Y-m-d H:i:s', (int) $recovery_timestamp) : __('Not scheduled', 'webdigitech-woo-product-emailer'),
			'cleanup_next_run'   => $cleanup_timestamp !== false ? wp_date('Y-m-d H:i:s', (int) $cleanup_timestamp) : __('Not scheduled', 'webdigitech-woo-product-emailer'),
			'last_retry_run'     => self::format_option_datetime((string) get_option(Constants::OPTION_LAST_RETRY_RUN, '')),
			'last_recovery_run'  => self::format_option_datetime((string) get_option(Constants::OPTION_LAST_RECOVERY_RUN, '')),
			'last_cleanup_run'   => self::format_option_datetime((string) get_option(Constants::OPTION_LAST_CLEANUP_RUN, '')),
		);
	}

	/**
	 * Събира storage / filesystem статус.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_storage_status(): array
	{
		$plugin_dir         = defined('WDT_WCPE_DIR') ? (string) WDT_WCPE_DIR : '';
		$assets_dir         = $plugin_dir !== '' ? $plugin_dir . 'assets/' : '';
		$admin_css          = $plugin_dir !== '' ? $plugin_dir . 'assets/css/admin.css' : '';
		$admin_js           = $plugin_dir !== '' ? $plugin_dir . 'assets/js/admin.js' : '';
		$product_panel_css  = $plugin_dir !== '' ? $plugin_dir . 'assets/css/product-panel.css' : '';
		$product_panel_js   = $plugin_dir !== '' ? $plugin_dir . 'assets/js/product-panel.js' : '';

		return array(
			'plugin_dir'          => $plugin_dir !== '' ? $plugin_dir : __('Not available', 'webdigitech-woo-product-emailer'),
			'plugin_dir_exists'   => $plugin_dir !== '' && is_dir($plugin_dir),
			'plugin_dir_readable' => $plugin_dir !== '' && is_readable($plugin_dir),
			'assets_dir_exists'   => $assets_dir !== '' && is_dir($assets_dir),
			'admin_css_exists'    => $admin_css !== '' && file_exists($admin_css),
			'admin_js_exists'     => $admin_js !== '' && file_exists($admin_js),
			'product_css_exists'  => $product_panel_css !== '' && file_exists($product_panel_css),
			'product_js_exists'   => $product_panel_js !== '' && file_exists($product_panel_js),
		);
	}

	/**
	 * Рендерира summary картите.
	 *
	 * @param array<string, mixed> $environment Environment данни.
	 * @param array<string, mixed> $plugin_status Plugin статус.
	 * @param array<string, mixed> $cron_status Cron статус.
	 * @param array<string, mixed> $storage_status Storage статус.
	 * @return void
	 */
	private static function render_health_cards(
		array $environment,
		array $plugin_status,
		array $cron_status,
		array $storage_status
	): void {
		$environment_ok = version_compare((string) $environment['php_version'], '7.4', '>=')
			&& ! empty($environment['wp_version']);

		$plugin_ok = (bool) $plugin_status['plugin_enabled']
			&& (bool) $plugin_status['logs_table_exists'];

		$cron_ok = (bool) $cron_status['retry_scheduled']
			|| (bool) $cron_status['recovery_scheduled']
			|| (bool) $cron_status['cleanup_scheduled'];

		$storage_ok = (bool) $storage_status['plugin_dir_exists']
			&& (bool) $storage_status['assets_dir_exists'];

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:18px 0 24px;">';

		self::render_health_card(
			__('Environment', 'webdigitech-woo-product-emailer'),
			$environment_ok ? __('Healthy', 'webdigitech-woo-product-emailer') : __('Needs attention', 'webdigitech-woo-product-emailer'),
			__('PHP, WordPress, WooCommerce and server runtime checks.', 'webdigitech-woo-product-emailer'),
			$environment_ok
		);

		self::render_health_card(
			__('Plugin Runtime', 'webdigitech-woo-product-emailer'),
			$plugin_ok ? __('Operational', 'webdigitech-woo-product-emailer') : __('Partially ready', 'webdigitech-woo-product-emailer'),
			__('Main plugin state, fallback readiness and database table availability.', 'webdigitech-woo-product-emailer'),
			$plugin_ok
		);

		self::render_health_card(
			__('Cron Scheduling', 'webdigitech-woo-product-emailer'),
			$cron_ok ? __('Detected', 'webdigitech-woo-product-emailer') : __('No schedule detected', 'webdigitech-woo-product-emailer'),
			__('Retry, recovery and cleanup background events.', 'webdigitech-woo-product-emailer'),
			$cron_ok
		);

		self::render_health_card(
			__('Assets & Storage', 'webdigitech-woo-product-emailer'),
			$storage_ok ? __('Available', 'webdigitech-woo-product-emailer') : __('Missing files', 'webdigitech-woo-product-emailer'),
			__('Plugin directory, assets folder and admin frontend files.', 'webdigitech-woo-product-emailer'),
			$storage_ok
		);

		echo '</div>';
	}

	/**
	 * Рендерира единична health карта.
	 *
	 * @param string $title Заглавие.
	 * @param string $value Стойност.
	 * @param string $description Описание.
	 * @param bool   $is_positive Позитивен статус.
	 * @return void
	 */
	private static function render_health_card(
		string $title,
		string $value,
		string $description,
		bool $is_positive
	): void {
		$accent = $is_positive ? '#2e7d32' : '#b26a00';

		echo '<div class="card" style="padding:16px 18px;border-left:4px solid ' . esc_attr($accent) . ';">';
		echo '<h2 style="margin:0 0 10px;">' . esc_html($title) . '</h2>';
		echo '<p style="margin:0 0 8px;font-size:16px;font-weight:600;">' . esc_html($value) . '</p>';
		echo '<p style="margin:0;color:#50575e;">' . esc_html($description) . '</p>';
		echo '</div>';
	}

	/**
	 * Рендерира environment секцията.
	 *
	 * @param array<string, mixed> $environment Environment данни.
	 * @return void
	 */
	private static function render_environment_section(array $environment): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Environment', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<table class="widefat striped" style="max-width:980px;">';
		echo '<thead><tr><th>' . esc_html__('Check', 'webdigitech-woo-product-emailer') . '</th><th>' . esc_html__('Value', 'webdigitech-woo-product-emailer') . '</th></tr></thead>';
		echo '<tbody>';

		self::render_status_row(__('PHP Version', 'webdigitech-woo-product-emailer'), (string) $environment['php_version']);
		self::render_status_row(__('WordPress Version', 'webdigitech-woo-product-emailer'), (string) $environment['wp_version']);
		self::render_status_row(__('WooCommerce Version', 'webdigitech-woo-product-emailer'), (string) $environment['wc_version']);
		self::render_status_row(__('MySQL Version', 'webdigitech-woo-product-emailer'), (string) $environment['mysql_version']);
		self::render_status_row(__('Memory Limit', 'webdigitech-woo-product-emailer'), (string) $environment['memory_limit']);
		self::render_status_row(__('Max Execution Time', 'webdigitech-woo-product-emailer'), (string) $environment['max_execution_time']);
		self::render_status_row(__('WP_DEBUG', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $environment['wp_debug']));
		self::render_status_row(__('DISABLE_WP_CRON', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $environment['wp_cron_disabled']));
		self::render_status_row(__('SSL Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $environment['is_ssl']));
		self::render_status_row(__('Site URL', 'webdigitech-woo-product-emailer'), (string) $environment['site_url']);
		self::render_status_row(__('Admin Email', 'webdigitech-woo-product-emailer'), (string) $environment['admin_email']);

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Рендерира plugin runtime секцията.
	 *
	 * @param array<string, mixed> $plugin_status Plugin статус.
	 * @return void
	 */
	private static function render_plugin_section(array $plugin_status): void
	{
		$allowed_statuses = is_array($plugin_status['allowed_order_statuses'])
			? implode(', ', array_map('strval', $plugin_status['allowed_order_statuses']))
			: '';

		if ($allowed_statuses === '') {
			$allowed_statuses = __('No statuses selected', 'webdigitech-woo-product-emailer');
		}

		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Plugin Runtime', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<table class="widefat striped" style="max-width:980px;">';
		echo '<thead><tr><th>' . esc_html__('Check', 'webdigitech-woo-product-emailer') . '</th><th>' . esc_html__('Value', 'webdigitech-woo-product-emailer') . '</th></tr></thead>';
		echo '<tbody>';

		self::render_status_row(__('Plugin Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['plugin_enabled']));
		self::render_status_row(__('Fallback Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['fallback_enabled']));
		self::render_status_row(__('Fallback Template Valid', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['fallback_template_valid']));
		self::render_status_row(__('Fallback Subject Present', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['fallback_subject_present']));
		self::render_status_row(__('Fallback HTML Present', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['fallback_html_present']));
		self::render_status_row(__('Fallback Text Present', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['fallback_text_present']));
		self::render_status_row(__('Logging Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['logging_enabled']));
		self::render_status_row(__('Retry Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['retry_enabled']));
		self::render_status_row(__('Recovery Enabled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['recovery_enabled']));
		self::render_status_row(__('Debug Mode', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['debug_mode']));
		self::render_status_row(__('Preserve Data on Uninstall', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['preserve_on_uninstall']));
		self::render_status_row(__('Logs Table Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['logs_table_exists']));
		self::render_status_row(__('Test Email Recipient Set', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $plugin_status['test_email_recipient_set']));
		self::render_status_row(__('Mail Content Type', 'webdigitech-woo-product-emailer'), (string) $plugin_status['mail_content_type_mode']);
		self::render_status_row(__('Allowed Order Statuses', 'webdigitech-woo-product-emailer'), $allowed_statuses);
		self::render_status_row(
			__('Sender Name Override', 'webdigitech-woo-product-emailer'),
			(string) $plugin_status['sender_name_override'] !== '' ? (string) $plugin_status['sender_name_override'] : __('Not set', 'webdigitech-woo-product-emailer')
		);
		self::render_status_row(
			__('Sender Email Override', 'webdigitech-woo-product-emailer'),
			(string) $plugin_status['sender_email_override'] !== '' ? (string) $plugin_status['sender_email_override'] : __('Not set', 'webdigitech-woo-product-emailer')
		);
		self::render_status_row(
			__('Last Test Email Sent At', 'webdigitech-woo-product-emailer'),
			(string) $plugin_status['last_test_email_sent_at'] !== '' ? (string) $plugin_status['last_test_email_sent_at'] : __('No record', 'webdigitech-woo-product-emailer')
		);
		self::render_status_row(
			__('Last Test Email Error', 'webdigitech-woo-product-emailer'),
			(string) $plugin_status['last_test_email_error'] !== '' ? (string) $plugin_status['last_test_email_error'] : __('No stored error', 'webdigitech-woo-product-emailer')
		);

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Рендерира cron секцията.
	 *
	 * @param array<string, mixed> $cron_status Cron статус.
	 * @return void
	 */
	private static function render_cron_section(array $cron_status): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Cron Scheduling', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<table class="widefat striped" style="max-width:980px;">';
		echo '<thead><tr><th>' . esc_html__('Check', 'webdigitech-woo-product-emailer') . '</th><th>' . esc_html__('Value', 'webdigitech-woo-product-emailer') . '</th></tr></thead>';
		echo '<tbody>';

		self::render_status_row(__('Retry Hook', 'webdigitech-woo-product-emailer'), (string) $cron_status['retry_hook']);
		self::render_status_row(__('Retry Scheduled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $cron_status['retry_scheduled']));
		self::render_status_row(__('Retry Next Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['retry_next_run']);
		self::render_status_row(__('Last Retry Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['last_retry_run']);

		self::render_status_row(__('Recovery Hook', 'webdigitech-woo-product-emailer'), (string) $cron_status['recovery_hook']);
		self::render_status_row(__('Recovery Scheduled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $cron_status['recovery_scheduled']));
		self::render_status_row(__('Recovery Next Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['recovery_next_run']);
		self::render_status_row(__('Last Recovery Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['last_recovery_run']);

		self::render_status_row(__('Cleanup Hook', 'webdigitech-woo-product-emailer'), (string) $cron_status['cleanup_hook']);
		self::render_status_row(__('Cleanup Scheduled', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $cron_status['cleanup_scheduled']));
		self::render_status_row(__('Cleanup Next Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['cleanup_next_run']);
		self::render_status_row(__('Last Cleanup Run', 'webdigitech-woo-product-emailer'), (string) $cron_status['last_cleanup_run']);

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Рендерира storage секцията.
	 *
	 * @param array<string, mixed> $storage_status Storage статус.
	 * @return void
	 */
	private static function render_storage_section(array $storage_status): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Assets & Storage', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<table class="widefat striped" style="max-width:980px;">';
		echo '<thead><tr><th>' . esc_html__('Check', 'webdigitech-woo-product-emailer') . '</th><th>' . esc_html__('Value', 'webdigitech-woo-product-emailer') . '</th></tr></thead>';
		echo '<tbody>';

		self::render_status_row(__('Plugin Directory', 'webdigitech-woo-product-emailer'), (string) $storage_status['plugin_dir']);
		self::render_status_row(__('Plugin Directory Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['plugin_dir_exists']));
		self::render_status_row(__('Plugin Directory Readable', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['plugin_dir_readable']));
		self::render_status_row(__('Assets Directory Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['assets_dir_exists']));
		self::render_status_row(__('Admin CSS Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['admin_css_exists']));
		self::render_status_row(__('Admin JS Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['admin_js_exists']));
		self::render_status_row(__('Product Panel CSS Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['product_css_exists']));
		self::render_status_row(__('Product Panel JS Exists', 'webdigitech-woo-product-emailer'), self::bool_label((bool) $storage_status['product_js_exists']));

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Рендерира status row.
	 *
	 * @param string $label Етикет.
	 * @param string $value Стойност.
	 * @return void
	 */
	private static function render_status_row(string $label, string $value): void
	{
		echo '<tr>';
		echo '<td>' . esc_html($label) . '</td>';
		echo '<td>' . esc_html($value) . '</td>';
		echo '</tr>';
	}

	/**
	 * Форматира bool в четим label.
	 *
	 * @param bool $value Стойност.
	 * @return string
	 */
	private static function bool_label(bool $value): string
	{
		return $value
			? __('Yes', 'webdigitech-woo-product-emailer')
			: __('No', 'webdigitech-woo-product-emailer');
	}

	/**
	 * Връща името на logs таблицата.
	 *
	 * @return string
	 */
	private static function get_logs_table_name(): string
	{
		return Constants::log_table_name();
	}

	/**
	 * Форматира datetime стойност от option.
	 *
	 * @param string $value Сурова стойност.
	 * @return string
	 */
	private static function format_option_datetime(string $value): string
	{
		return $value !== '' ? $value : __('No record', 'webdigitech-woo-product-emailer');
	}

	/**
	 * Проверява дали таблица съществува.
	 *
	 * @param string $table_name Име на таблицата.
	 * @return bool
	 */
	private static function table_exists(string $table_name): bool
	{
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
		);

		return is_string($found) && $found === $table_name;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}