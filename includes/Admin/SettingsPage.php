<?php
/**
 * Settings страница на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Рендерира и обработва глобалните настройки на плъгина.
 */
final class SettingsPage
{
	/**
	 * Action key за save формата.
	 */
	private const FORM_ACTION = 'wdt_wcpe_save_settings';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'wdt_wcpe_save_settings_nonce';

	/**
	 * Рендерира settings страницата.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		Capabilities::enforce_manage_capability();

		$notice = self::handle_submit();
		$settings = Helpers::get_settings();

		echo '<div class="wrap wdt-wcpe-admin-wrap">';
		echo '<h1>' . esc_html__('Product Email Settings', 'webdigitech-woo-product-emailer') . '</h1>';
		echo '<p>' . esc_html__('Configure the global fallback template, delivery rules, logging, retry logic and diagnostics for product-based customer emails.', 'webdigitech-woo-product-emailer') . '</p>';

		self::render_notice($notice);
		self::render_overview_cards($settings);

		echo '<form method="post" action="">';

		wp_nonce_field(self::NONCE_ACTION, '_wdt_wcpe_nonce');

		echo '<input type="hidden" name="wdt_wcpe_action" value="' . esc_attr(self::FORM_ACTION) . '">';

		self::render_general_section($settings);
		self::render_fallback_template_section($settings);
		self::render_delivery_section($settings);
		self::render_logging_section($settings);
		self::render_placeholder_help_section();

		echo '<p class="submit" style="margin-top: 24px;">';
		submit_button(
			__('Save Settings', 'webdigitech-woo-product-emailer'),
			'primary',
			'submit',
			false
		);
		echo '</p>';

		echo '</form>';

		FooterRenderer::render();

		echo '</div>';
	}

	/**
	 * Обработва submit-а на формата.
	 *
	 * @return array<string, string>|null
	 */
	private static function handle_submit(): ?array
	{
		if (! isset($_POST['wdt_wcpe_action'])) {
			return null;
		}

		$action = Helpers::sanitize_text(wp_unslash($_POST['wdt_wcpe_action']));

		if ($action !== self::FORM_ACTION) {
			return null;
		}

		if (! isset($_POST['_wdt_wcpe_nonce']) || ! wp_verify_nonce(wp_unslash($_POST['_wdt_wcpe_nonce']), self::NONCE_ACTION)) {
			return array(
				'type'    => 'error',
				'message' => __('Security check failed. Please reload the page and try again.', 'webdigitech-woo-product-emailer'),
			);
		}

		if (! Capabilities::current_user_can_manage()) {
			return array(
				'type'    => 'error',
				'message' => __('You are not allowed to manage these settings.', 'webdigitech-woo-product-emailer'),
			);
		}

		$raw = wp_unslash($_POST);
		$sanitized = self::sanitize_settings_input($raw);

		update_option(Constants::OPTION_SETTINGS, $sanitized, false);
		update_option(
			Constants::OPTION_PRESERVE_DATA_ON_UNINSTALL,
			$sanitized['preserve_data_on_uninstall'],
			false
		);

		Logger::log_debug(
			'Settings updated from admin settings page.',
			array(
				'updated_by' => get_current_user_id(),
				'page'       => Constants::MENU_SLUG_SETTINGS,
			)
		);

		return array(
			'type'    => 'success',
			'message' => __('Settings saved successfully.', 'webdigitech-woo-product-emailer'),
		);
	}

	/**
	 * Санитизира входните настройки.
	 *
	 * @param array<string, mixed> $raw Raw POST data.
	 * @return array<string, mixed>
	 */
	private static function sanitize_settings_input(array $raw): array
	{
		$defaults = Constants::default_settings();

		$allowed_order_statuses = self::sanitize_allowed_statuses(
			Helpers::array_get($raw, 'allowed_order_statuses', array())
		);

		$fallback_body_html = Helpers::sanitize_template_html(
			Helpers::array_get($raw, 'fallback_email_body_html', '')
		);

		$fallback_body_text = Helpers::sanitize_template_text(
			Helpers::array_get($raw, 'fallback_email_body_text', '')
		);

		if ($fallback_body_text === '' && $fallback_body_html !== '') {
			$fallback_body_text = Helpers::html_to_text($fallback_body_html);
		}

		$mail_content_type_mode = Helpers::sanitize_text(
			Helpers::array_get($raw, 'mail_content_type_mode', Constants::DEFAULT_MAIL_CONTENT_TYPE)
		);

		if (! in_array($mail_content_type_mode, Constants::ALLOWED_MAIL_CONTENT_TYPES, true)) {
			$mail_content_type_mode = Constants::DEFAULT_MAIL_CONTENT_TYPE;
		}

		$settings = array(
			'plugin_enabled'             => self::checkbox_to_yes_no($raw, 'plugin_enabled', 'yes'),
			'fallback_email_enabled'     => self::checkbox_to_yes_no($raw, 'fallback_email_enabled', 'yes'),
			'fallback_email_subject'     => Helpers::sanitize_email_subject(
				Helpers::array_get($raw, 'fallback_email_subject', '')
			),
			'fallback_email_heading'     => Helpers::sanitize_text(
				Helpers::array_get($raw, 'fallback_email_heading', '')
			),
			'fallback_email_body_html'   => $fallback_body_html,
			'fallback_email_body_text'   => $fallback_body_text,
			'allowed_order_statuses'     => ! empty($allowed_order_statuses)
				? $allowed_order_statuses
				: Constants::DEFAULT_ALLOWED_ORDER_STATUSES,
			'enable_logging'             => self::checkbox_to_yes_no($raw, 'enable_logging', 'yes'),
			'retain_logs_days'           => Helpers::normalize_int(
				Helpers::array_get($raw, 'retain_logs_days', Constants::DEFAULT_LOG_RETENTION_DAYS),
				Constants::DEFAULT_LOG_RETENTION_DAYS,
				1,
				3650
			),
			'retry_failed_sends'         => self::checkbox_to_yes_no($raw, 'retry_failed_sends', 'yes'),
			'retry_interval_minutes'     => Helpers::normalize_int(
				Helpers::array_get($raw, 'retry_interval_minutes', Constants::DEFAULT_RETRY_INTERVAL_MINUTES),
				Constants::DEFAULT_RETRY_INTERVAL_MINUTES,
				1,
				1440
			),
			'max_retry_attempts'         => Helpers::normalize_int(
				Helpers::array_get($raw, 'max_retry_attempts', Constants::DEFAULT_MAX_RETRY_ATTEMPTS),
				Constants::DEFAULT_MAX_RETRY_ATTEMPTS,
				1,
				20
			),
			'test_email_recipient'       => Helpers::sanitize_email_address(
				Helpers::array_get($raw, 'test_email_recipient', '')
			),
			'mail_content_type_mode'     => $mail_content_type_mode,
			'sender_name_override'       => Helpers::sanitize_text(
				Helpers::array_get($raw, 'sender_name_override', '')
			),
			'sender_email_override'      => Helpers::sanitize_email_address(
				Helpers::array_get($raw, 'sender_email_override', '')
			),
			'preserve_data_on_uninstall' => self::checkbox_to_yes_no($raw, 'preserve_data_on_uninstall', 'yes'),
			'debug_mode'                 => self::checkbox_to_yes_no($raw, 'debug_mode', 'no'),
			'recovery_enabled'           => self::checkbox_to_yes_no($raw, 'recovery_enabled', 'yes'),
			'recovery_lookback_hours'    => Helpers::normalize_int(
				Helpers::array_get($raw, 'recovery_lookback_hours', 24),
				24,
				1,
				720
			),
			'recovery_batch_limit'       => Helpers::normalize_int(
				Helpers::array_get($raw, 'recovery_batch_limit', Constants::RECOVERY_BATCH_LIMIT),
				Constants::RECOVERY_BATCH_LIMIT,
				1,
				500
			),
			'retry_batch_limit'          => Helpers::normalize_int(
				Helpers::array_get($raw, 'retry_batch_limit', Constants::RETRY_BATCH_LIMIT),
				Constants::RETRY_BATCH_LIMIT,
				1,
				500
			),
		);

		return wp_parse_args($settings, $defaults);
	}

	/**
	 * Нормализира checkbox към yes/no.
	 *
	 * @param array<string, mixed> $raw Raw POST data.
	 * @param string               $key Ключ на полето.
	 * @param string               $default Default yes/no.
	 * @return string
	 */
	private static function checkbox_to_yes_no(array $raw, string $key, string $default = 'no'): string
	{
		return array_key_exists($key, $raw) ? 'yes' : Helpers::sanitize_yes_no($default, 'no');
	}

	/**
	 * Санитизира масив от Woo order statuses.
	 *
	 * @param mixed $statuses Стойност от POST.
	 * @return string[]
	 */
	private static function sanitize_allowed_statuses(mixed $statuses): array
	{
		if (! is_array($statuses)) {
			return array();
		}

		$available_statuses = array_keys(self::get_wc_order_status_options());
		$normalized = array();

		foreach ($statuses as $status) {
			$status = Helpers::sanitize_text($status);

			if ($status === '') {
				continue;
			}

			if (in_array($status, $available_statuses, true)) {
				$normalized[] = $status;
			}
		}

		return array_values(array_unique($normalized));
	}

	/**
	 * Рендерира notice block.
	 *
	 * @param array<string, string>|null $notice Notice payload.
	 * @return void
	 */
	private static function render_notice(?array $notice): void
	{
		if (! is_array($notice) || empty($notice['message'])) {
			return;
		}

		$type = isset($notice['type']) && $notice['type'] === 'error' ? 'notice-error' : 'notice-success';

		echo '<div class="notice ' . esc_attr($type) . ' is-dismissible"><p>';
		echo esc_html($notice['message']);
		echo '</p></div>';
	}

	/**
	 * Рендерира overview карти.
	 *
	 * @param array<string, mixed> $settings Текущи настройки.
	 * @return void
	 */
	private static function render_overview_cards(array $settings): void
	{
		$last_test_email_sent_at = get_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, '');
		$last_test_email_error = get_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, '');

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:18px 0 24px;">';

		self::render_overview_card(
			__('Plugin Status', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'plugin_enabled', 'yes'))
				? __('Enabled', 'webdigitech-woo-product-emailer')
				: __('Disabled', 'webdigitech-woo-product-emailer'),
			__('Global processing switch for the whole plugin.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Fallback Template', 'webdigitech-woo-product-emailer'),
			Helpers::has_valid_fallback_template()
				? __('Ready', 'webdigitech-woo-product-emailer')
				: __('Incomplete', 'webdigitech-woo-product-emailer'),
			__('Used when a product has no dedicated email template.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Retry / Recovery', 'webdigitech-woo-product-emailer'),
			sprintf(
				/* translators: 1: retry status, 2: recovery status */
				__('Retry: %1$s | Recovery: %2$s', 'webdigitech-woo-product-emailer'),
				Helpers::is_yes(Helpers::array_get($settings, 'retry_failed_sends', 'yes'))
					? __('On', 'webdigitech-woo-product-emailer')
					: __('Off', 'webdigitech-woo-product-emailer'),
				Helpers::is_yes(Helpers::array_get($settings, 'recovery_enabled', 'yes'))
					? __('On', 'webdigitech-woo-product-emailer')
					: __('Off', 'webdigitech-woo-product-emailer')
			),
			__('Background safety logic for failed or missed sends.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Last Test Email', 'webdigitech-woo-product-emailer'),
			$last_test_email_sent_at !== ''
				? (string) $last_test_email_sent_at
				: __('No test email sent yet.', 'webdigitech-woo-product-emailer'),
			$last_test_email_error !== ''
				? sprintf(
					/* translators: %s: error message */
					__('Last error: %s', 'webdigitech-woo-product-emailer'),
					(string) $last_test_email_error
				)
				: __('No recent test email error stored.', 'webdigitech-woo-product-emailer')
		);

		echo '</div>';
	}

	/**
	 * Рендерира единична overview карта.
	 *
	 * @param string $title Заглавие.
	 * @param string $value Основна стойност.
	 * @param string $description Описание.
	 * @return void
	 */
	private static function render_overview_card(string $title, string $value, string $description): void
	{
		echo '<div class="card" style="padding:16px 18px;">';
		echo '<h2 style="margin:0 0 10px;">' . esc_html($title) . '</h2>';
		echo '<p style="margin:0 0 8px;font-size:16px;font-weight:600;">' . esc_html($value) . '</p>';
		echo '<p style="margin:0;color:#50575e;">' . esc_html($description) . '</p>';
		echo '</div>';
	}

	/**
	 * Рендерира секцията General.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return void
	 */
	private static function render_general_section(array $settings): void
	{
		$order_status_options = self::get_wc_order_status_options();
		$selected_statuses = Helpers::get_allowed_order_statuses();

		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('General Rules', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p style="margin-top:0;color:#50575e;">' . esc_html__('Control whether the plugin is active globally, whether the fallback template can be used and on which WooCommerce order statuses customer emails may be dispatched.', 'webdigitech-woo-product-emailer') . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_checkbox_row(
			'plugin_enabled',
			__('Enable plugin processing', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'plugin_enabled', 'yes')),
			__('Disable this only if you want to keep the plugin installed but stop all customer email dispatching.', 'webdigitech-woo-product-emailer')
		);

		self::render_checkbox_row(
			'fallback_email_enabled',
			__('Enable fallback email template', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'fallback_email_enabled', 'yes')),
			__('When enabled, products without a custom template may use the global fallback template configured below.', 'webdigitech-woo-product-emailer')
		);

		echo '<tr>';
		echo '<th scope="row"><label>' . esc_html__('Allowed order statuses', 'webdigitech-woo-product-emailer') . '</label></th>';
		echo '<td>';
		echo '<fieldset>';
		echo '<legend class="screen-reader-text"><span>' . esc_html__('Allowed order statuses', 'webdigitech-woo-product-emailer') . '</span></legend>';

		foreach ($order_status_options as $status_key => $status_label) {
			$is_checked = in_array($status_key, $selected_statuses, true);

			echo '<label style="display:inline-flex;align-items:center;margin:0 18px 8px 0;">';
			echo '<input type="checkbox" name="allowed_order_statuses[]" value="' . esc_attr($status_key) . '" ' . checked($is_checked, true, false) . '>';
			echo '<span style="margin-left:8px;">' . esc_html($status_label) . '</span>';
			echo '</label>';
		}

		echo '<p class="description">' . esc_html__('Use WooCommerce statuses without the wc- prefix. The plugin will only send customer emails when the order reaches one of the selected statuses.', 'webdigitech-woo-product-emailer') . '</p>';
		echo '</fieldset>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Рендерира секцията за fallback template.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return void
	 */
	private static function render_fallback_template_section(array $settings): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Fallback Email Template', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p style="margin-top:0;color:#50575e;">' . esc_html__('This template is used when a product does not have its own custom email content. Keep the subject and at least one body format filled in if you want fallback delivery to work.', 'webdigitech-woo-product-emailer') . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_text_input_row(
			'fallback_email_subject',
			__('Fallback subject', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'fallback_email_subject', ''),
			__('Example: Your access details for {product_name}', 'webdigitech-woo-product-emailer')
		);

		self::render_text_input_row(
			'fallback_email_heading',
			__('Fallback heading', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'fallback_email_heading', ''),
			__('Optional heading used inside the email template.', 'webdigitech-woo-product-emailer')
		);

		self::render_textarea_row(
			'fallback_email_body_html',
			__('Fallback HTML body', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'fallback_email_body_html', ''),
			8,
			__('You may use safe HTML tags such as links, paragraphs, lists, strong, em and line breaks.', 'webdigitech-woo-product-emailer')
		);

		self::render_textarea_row(
			'fallback_email_body_text',
			__('Fallback plain text body', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'fallback_email_body_text', ''),
			8,
			__('Optional plain text version. If left empty and HTML exists, the plugin can derive a text fallback automatically on save.', 'webdigitech-woo-product-emailer')
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Рендерира секцията Delivery / Sender.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return void
	 */
	private static function render_delivery_section(array $settings): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Delivery and Sender Settings', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p style="margin-top:0;color:#50575e;">' . esc_html__('Configure the default recipient for test emails, the message content type and optional sender overrides used by the mailer.', 'webdigitech-woo-product-emailer') . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_text_input_row(
			'test_email_recipient',
			__('Default test email recipient', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'test_email_recipient', ''),
			__('This address can be prefilled on the Tools page for manual test sends.', 'webdigitech-woo-product-emailer'),
			'email'
		);

		echo '<tr>';
		echo '<th scope="row"><label for="mail_content_type_mode">' . esc_html__('Mail content type', 'webdigitech-woo-product-emailer') . '</label></th>';
		echo '<td>';
		echo '<select name="mail_content_type_mode" id="mail_content_type_mode">';
		foreach (Constants::ALLOWED_MAIL_CONTENT_TYPES as $content_type) {
			$label = $content_type === 'text/plain'
				? __('Plain text only', 'webdigitech-woo-product-emailer')
				: __('HTML email', 'webdigitech-woo-product-emailer');

			echo '<option value="' . esc_attr($content_type) . '" ' . selected(
				(string) Helpers::array_get($settings, 'mail_content_type_mode', Constants::DEFAULT_MAIL_CONTENT_TYPE),
				$content_type,
				false
			) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Defines the preferred mail content type used by the mailer.', 'webdigitech-woo-product-emailer') . '</p>';
		echo '</td>';
		echo '</tr>';

		self::render_text_input_row(
			'sender_name_override',
			__('Sender name override', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'sender_name_override', ''),
			__('Optional. Leave empty to let WordPress / WooCommerce use the default sender name.', 'webdigitech-woo-product-emailer')
		);

		self::render_text_input_row(
			'sender_email_override',
			__('Sender email override', 'webdigitech-woo-product-emailer'),
			(string) Helpers::array_get($settings, 'sender_email_override', ''),
			__('Optional. Leave empty to use the default sender email configured by the site.', 'webdigitech-woo-product-emailer'),
			'email'
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Рендерира logging / retry / recovery секцията.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return void
	 */
	private static function render_logging_section(array $settings): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Logging, Retry and Recovery', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p style="margin-top:0;color:#50575e;">' . esc_html__('These settings control database logging, retry attempts for failed sends, recovery scans for missed dispatches and low-level debugging behavior.', 'webdigitech-woo-product-emailer') . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_checkbox_row(
			'enable_logging',
			__('Enable database logging', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'enable_logging', 'yes')),
			__('Stores dispatch history and operational records in the plugin log table.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'retain_logs_days',
			__('Log retention in days', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'retain_logs_days', Constants::DEFAULT_LOG_RETENTION_DAYS),
			1,
			3650,
			__('How long log entries should be retained before cleanup routines may remove them.', 'webdigitech-woo-product-emailer')
		);

		self::render_checkbox_row(
			'retry_failed_sends',
			__('Enable retry for failed sends', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'retry_failed_sends', 'yes')),
			__('Allows cron-based retry attempts for dispatches that previously failed.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'retry_interval_minutes',
			__('Retry interval in minutes', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'retry_interval_minutes', Constants::DEFAULT_RETRY_INTERVAL_MINUTES),
			1,
			1440,
			__('Minimum delay between retry attempts.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'max_retry_attempts',
			__('Maximum retry attempts', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'max_retry_attempts', Constants::DEFAULT_MAX_RETRY_ATTEMPTS),
			1,
			20,
			__('Upper limit for how many times a failed dispatch may be retried.', 'webdigitech-woo-product-emailer')
		);

		self::render_checkbox_row(
			'recovery_enabled',
			__('Enable recovery scans', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'recovery_enabled', 'yes')),
			__('Allows the plugin to scan recent paid orders for missed dispatch opportunities.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'recovery_lookback_hours',
			__('Recovery lookback in hours', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'recovery_lookback_hours', 24),
			1,
			720,
			__('How far back recovery scans should inspect eligible orders.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'recovery_batch_limit',
			__('Recovery batch limit', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'recovery_batch_limit', Constants::RECOVERY_BATCH_LIMIT),
			1,
			500,
			__('Maximum number of candidate records handled in a single recovery run.', 'webdigitech-woo-product-emailer')
		);

		self::render_number_input_row(
			'retry_batch_limit',
			__('Retry batch limit', 'webdigitech-woo-product-emailer'),
			(int) Helpers::array_get($settings, 'retry_batch_limit', Constants::RETRY_BATCH_LIMIT),
			1,
			500,
			__('Maximum number of failed dispatches processed in a single retry run.', 'webdigitech-woo-product-emailer')
		);

		self::render_checkbox_row(
			'debug_mode',
			__('Enable debug mode', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'debug_mode', 'no')),
			__('When enabled, the plugin may write additional diagnostics to the PHP error log.', 'webdigitech-woo-product-emailer')
		);

		self::render_checkbox_row(
			'preserve_data_on_uninstall',
			__('Preserve data on uninstall', 'webdigitech-woo-product-emailer'),
			Helpers::is_yes(Helpers::array_get($settings, 'preserve_data_on_uninstall', 'yes')),
			__('Keep options and custom plugin data when the plugin is uninstalled.', 'webdigitech-woo-product-emailer')
		);

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Рендерира справка за placeholders.
	 *
	 * @return void
	 */
	private static function render_placeholder_help_section(): void
	{
		$placeholders = array(
			'{customer_first_name}' => __('Customer first name', 'webdigitech-woo-product-emailer'),
			'{customer_last_name}'  => __('Customer last name', 'webdigitech-woo-product-emailer'),
			'{customer_full_name}'  => __('Customer full name', 'webdigitech-woo-product-emailer'),
			'{customer_email}'      => __('Customer email address', 'webdigitech-woo-product-emailer'),
			'{order_id}'            => __('WooCommerce order ID', 'webdigitech-woo-product-emailer'),
			'{order_number}'        => __('WooCommerce order number', 'webdigitech-woo-product-emailer'),
			'{order_date}'          => __('Order date', 'webdigitech-woo-product-emailer'),
			'{product_id}'          => __('WooCommerce product ID', 'webdigitech-woo-product-emailer'),
			'{product_name}'        => __('Product name', 'webdigitech-woo-product-emailer'),
			'{site_name}'           => __('Website name', 'webdigitech-woo-product-emailer'),
			'{site_url}'            => __('Website URL', 'webdigitech-woo-product-emailer'),
		);

		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Available Template Placeholders', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p style="margin-top:0;color:#50575e;">' . esc_html__('Use these placeholders inside the subject, heading or body fields of your fallback template and product-specific templates.', 'webdigitech-woo-product-emailer') . '</p>';

		echo '<table class="widefat striped" style="max-width:760px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Placeholder', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th>' . esc_html__('Meaning', 'webdigitech-woo-product-emailer') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($placeholders as $token => $label) {
			echo '<tr>';
			echo '<td><code>' . esc_html($token) . '</code></td>';
			echo '<td>' . esc_html($label) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Връща Woo order statuses във формат status => label.
	 *
	 * @return array<string, string>
	 */
	private static function get_wc_order_status_options(): array
	{
		$options = array();

		if (function_exists('wc_get_order_statuses')) {
			$raw_statuses = wc_get_order_statuses();

			if (is_array($raw_statuses)) {
				foreach ($raw_statuses as $status_key => $status_label) {
					$normalized_key = str_replace('wc-', '', (string) $status_key);
					$options[$normalized_key] = wp_strip_all_tags((string) $status_label);
				}
			}
		}

		if (empty($options)) {
			$options = array(
				'pending'    => __('Pending payment', 'webdigitech-woo-product-emailer'),
				'processing' => __('Processing', 'webdigitech-woo-product-emailer'),
				'completed'  => __('Completed', 'webdigitech-woo-product-emailer'),
				'on-hold'    => __('On hold', 'webdigitech-woo-product-emailer'),
				'cancelled'  => __('Cancelled', 'webdigitech-woo-product-emailer'),
				'refunded'   => __('Refunded', 'webdigitech-woo-product-emailer'),
				'failed'     => __('Failed', 'webdigitech-woo-product-emailer'),
			);
		}

		return $options;
	}

	/**
	 * Рендерира checkbox ред.
	 *
	 * @param string $name Name attribute.
	 * @param string $label Label.
	 * @param bool   $checked Checked state.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_checkbox_row(string $name, string $label, bool $checked, string $description = ''): void
	{
		echo '<tr>';
		echo '<th scope="row">' . esc_html($label) . '</th>';
		echo '<td>';
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr($name) . '" value="yes" ' . checked($checked, true, false) . '>';
		echo '<span style="margin-left:8px;">' . esc_html__('Enabled', 'webdigitech-woo-product-emailer') . '</span>';
		echo '</label>';

		if ($description !== '') {
			echo '<p class="description">' . esc_html($description) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Рендерира text input ред.
	 *
	 * @param string $name Name/id.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $description Description.
	 * @param string $type Input type.
	 * @return void
	 */
	private static function render_text_input_row(
		string $name,
		string $label,
		string $value,
		string $description = '',
		string $type = 'text'
	): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
		echo '<td>';
		echo '<input class="regular-text" type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';

		if ($description !== '') {
			echo '<p class="description">' . esc_html($description) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Рендерира number input ред.
	 *
	 * @param string $name Name/id.
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @param int    $min Min.
	 * @param int    $max Max.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_number_input_row(
		string $name,
		string $label,
		int $value,
		int $min,
		int $max,
		string $description = ''
	): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
		echo '<td>';
		echo '<input class="small-text" type="number" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" value="' . esc_attr((string) $value) . '">';

		if ($description !== '') {
			echo '<p class="description">' . esc_html($description) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Рендерира textarea ред.
	 *
	 * @param string $name Name/id.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param int    $rows Rows.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_textarea_row(
		string $name,
		string $label,
		string $value,
		int $rows = 6,
		string $description = ''
	): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
		echo '<td>';
		echo '<textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '" class="large-text code">' . esc_textarea($value) . '</textarea>';

		if ($description !== '') {
			echo '<p class="description">' . esc_html($description) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}