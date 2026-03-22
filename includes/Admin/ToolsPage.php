<?php
/**
 * Tools страница на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

use WebDigiTech\WooProductEmailer\API\AjaxTestEmail;
use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Рендерира tools страницата на плъгина.
 */
final class ToolsPage
{
	/**
	 * Рендерира tools страницата.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		Capabilities::enforce_manage_capability();

		$settings = Helpers::get_settings();

		$test_email_recipient = (string) Helpers::array_get($settings, 'test_email_recipient', '');
		$fallback_subject = (string) Helpers::array_get($settings, 'fallback_email_subject', '');
		$fallback_heading = (string) Helpers::array_get($settings, 'fallback_email_heading', '');
		$fallback_html_body = (string) Helpers::array_get($settings, 'fallback_email_body_html', '');
		$fallback_text_body = (string) Helpers::array_get($settings, 'fallback_email_body_text', '');

		$is_plugin_enabled = Helpers::is_yes(Helpers::array_get($settings, 'plugin_enabled', 'yes'));
		$is_fallback_enabled = Helpers::is_yes(Helpers::array_get($settings, 'fallback_email_enabled', 'yes'));
		$is_logging_enabled = Helpers::is_yes(Helpers::array_get($settings, 'enable_logging', 'yes'));
		$is_retry_enabled = Helpers::is_yes(Helpers::array_get($settings, 'retry_failed_sends', 'yes'));
		$is_recovery_enabled = Helpers::is_yes(Helpers::array_get($settings, 'recovery_enabled', 'yes'));
		$has_valid_fallback_template = Helpers::has_valid_fallback_template();
		$has_ajax_test_email_handler = class_exists(AjaxTestEmail::class) && method_exists(AjaxTestEmail::class, 'register');
		$last_test_email_sent_at = (string) get_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, '');
		$last_test_email_error = (string) get_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, '');
		$allowed_order_statuses = Helpers::get_allowed_order_statuses();
		$allowed_order_statuses_label = ! empty($allowed_order_statuses)
			? implode(', ', array_map('strval', $allowed_order_statuses))
			: __('No statuses selected', 'webdigitech-woo-product-emailer');

		echo '<div class="wrap wdt-wcpe-admin-wrap">';
		echo '<h1>' . esc_html__('Tools', 'webdigitech-woo-product-emailer') . '</h1>';
		echo '<p>' . esc_html__('Use this page to verify operational readiness, prepare manual test sending and inspect the current runtime state of the plugin.', 'webdigitech-woo-product-emailer') . '</p>';

		self::render_status_cards(
			$is_plugin_enabled,
			$is_fallback_enabled,
			$has_valid_fallback_template,
			$has_ajax_test_email_handler,
			$is_logging_enabled,
			$is_retry_enabled,
			$is_recovery_enabled
		);

		self::render_test_email_section(
			$test_email_recipient,
			$fallback_subject,
			$fallback_heading,
			$fallback_html_body,
			$fallback_text_body,
			$has_ajax_test_email_handler,
			$last_test_email_sent_at,
			$last_test_email_error
		);

		self::render_runtime_summary_section(
			$allowed_order_statuses_label,
			$settings
		);

		self::render_manual_diagnostics_section(
			$is_plugin_enabled,
			$is_fallback_enabled,
			$has_valid_fallback_template,
			$test_email_recipient,
			$has_ajax_test_email_handler
		);

		FooterRenderer::render();

		echo '</div>';
	}

	/**
	 * Рендерира overview status карти.
	 *
	 * @param bool $is_plugin_enabled Плъгинът е активен.
	 * @param bool $is_fallback_enabled Fallback template е активен.
	 * @param bool $has_valid_fallback_template Има валиден fallback template.
	 * @param bool $has_ajax_test_email_handler Има test email AJAX handler.
	 * @param bool $is_logging_enabled Логването е активно.
	 * @param bool $is_retry_enabled Retry е активно.
	 * @param bool $is_recovery_enabled Recovery е активно.
	 * @return void
	 */
	private static function render_status_cards(
		bool $is_plugin_enabled,
		bool $is_fallback_enabled,
		bool $has_valid_fallback_template,
		bool $has_ajax_test_email_handler,
		bool $is_logging_enabled,
		bool $is_retry_enabled,
		bool $is_recovery_enabled
	): void {
		$items = array(
			array(
				'label' => __('Plugin Processing', 'webdigitech-woo-product-emailer'),
				'value' => $is_plugin_enabled ? __('Enabled', 'webdigitech-woo-product-emailer') : __('Disabled', 'webdigitech-woo-product-emailer'),
				'ok'    => $is_plugin_enabled,
			),
			array(
				'label' => __('Fallback Template', 'webdigitech-woo-product-emailer'),
				'value' => $is_fallback_enabled ? __('Enabled', 'webdigitech-woo-product-emailer') : __('Disabled', 'webdigitech-woo-product-emailer'),
				'ok'    => $is_fallback_enabled,
			),
			array(
				'label' => __('Fallback Template Validity', 'webdigitech-woo-product-emailer'),
				'value' => $has_valid_fallback_template ? __('Ready', 'webdigitech-woo-product-emailer') : __('Incomplete', 'webdigitech-woo-product-emailer'),
				'ok'    => $has_valid_fallback_template,
			),
			array(
				'label' => __('Test Email Backend', 'webdigitech-woo-product-emailer'),
				'value' => $has_ajax_test_email_handler ? __('Available', 'webdigitech-woo-product-emailer') : __('Missing', 'webdigitech-woo-product-emailer'),
				'ok'    => $has_ajax_test_email_handler,
			),
			array(
				'label' => __('Logging', 'webdigitech-woo-product-emailer'),
				'value' => $is_logging_enabled ? __('Enabled', 'webdigitech-woo-product-emailer') : __('Disabled', 'webdigitech-woo-product-emailer'),
				'ok'    => $is_logging_enabled,
			),
			array(
				'label' => __('Retry Failed Sends', 'webdigitech-woo-product-emailer'),
				'value' => $is_retry_enabled ? __('Enabled', 'webdigitech-woo-product-emailer') : __('Disabled', 'webdigitech-woo-product-emailer'),
				'ok'    => $is_retry_enabled,
			),
			array(
				'label' => __('Recovery Scan', 'webdigitech-woo-product-emailer'),
				'value' => $is_recovery_enabled ? __('Enabled', 'webdigitech-woo-product-emailer') : __('Disabled', 'webdigitech-woo-product-emailer'),
				'ok'    => $is_recovery_enabled,
			),
		);

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:18px 0 24px;">';

		foreach ($items as $item) {
			$badge_bg = $item['ok'] ? '#dcfce7' : '#fee2e2';
			$badge_color = $item['ok'] ? '#166534' : '#991b1b';

			echo '<div class="card" style="padding:16px 18px;">';
			echo '<h2 style="margin:0 0 10px;">' . esc_html((string) $item['label']) . '</h2>';
			echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:' . esc_attr($badge_bg) . ';color:' . esc_attr($badge_color) . ';font-weight:600;">' . esc_html((string) $item['value']) . '</span>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Рендерира секцията за тестов email.
	 *
	 * @param string $test_email_recipient Получател.
	 * @param string $fallback_subject Fallback subject.
	 * @param string $fallback_heading Fallback heading.
	 * @param string $fallback_html_body Fallback HTML body.
	 * @param string $fallback_text_body Fallback text body.
	 * @param bool   $has_ajax_test_email_handler Има AJAX handler.
	 * @param string $last_test_email_sent_at Последно изпратен тестов email.
	 * @param string $last_test_email_error Последна test email грешка.
	 * @return void
	 */
	private static function render_test_email_section(
		string $test_email_recipient,
		string $fallback_subject,
		string $fallback_heading,
		string $fallback_html_body,
		string $fallback_text_body,
		bool $has_ajax_test_email_handler,
		string $last_test_email_sent_at,
		string $last_test_email_error
	): void {
		$prefilled_subject = $fallback_subject !== ''
			? $fallback_subject
			: __('Test email from Product Email plugin', 'webdigitech-woo-product-emailer');

		$prefilled_heading = $fallback_heading !== ''
			? $fallback_heading
			: __('Test Email', 'webdigitech-woo-product-emailer');

		$prefilled_body = $fallback_text_body !== ''
			? $fallback_text_body
			: (
				$fallback_html_body !== ''
					? Helpers::html_to_text($fallback_html_body)
					: __('This is a diagnostic test email generated from the plugin tools page.', 'webdigitech-woo-product-emailer')
			);

		echo '<div id="wdt-wcpe-test-email-card" class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Send Test Email', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p>' . esc_html__('Use the current fallback content as a base, adjust the message if needed and send a diagnostic email to verify transport, sender configuration and template delivery.', 'webdigitech-woo-product-emailer') . '</p>';

		if ($has_ajax_test_email_handler) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('The test-email backend handler is available. The button below can now send the test email asynchronously.', 'webdigitech-woo-product-emailer') . '</p></div>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('The dedicated test-email AJAX handler is not finished yet. This page is ready, but the actual send action will be available after the backend handler is completed.', 'webdigitech-woo-product-emailer') . '</p></div>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		self::render_input_row(
			'wdt-wcpe-test-email-recipient',
			__('Recipient', 'webdigitech-woo-product-emailer'),
			$test_email_recipient,
			'email',
			__('Target address for the diagnostic email.', 'webdigitech-woo-product-emailer')
		);

		self::render_input_row(
			'wdt-wcpe-test-email-subject',
			__('Subject', 'webdigitech-woo-product-emailer'),
			$prefilled_subject,
			'text',
			__('You can use the current fallback subject as the starting point.', 'webdigitech-woo-product-emailer')
		);

		self::render_input_row(
			'wdt-wcpe-test-email-heading',
			__('Heading', 'webdigitech-woo-product-emailer'),
			$prefilled_heading,
			'text',
			__('Optional heading rendered at the top of the email.', 'webdigitech-woo-product-emailer')
		);

		self::render_textarea_row(
			'wdt-wcpe-test-email-body',
			__('Plain text body', 'webdigitech-woo-product-emailer'),
			$prefilled_body,
			8,
			__('This text is sent to the AJAX handler as the test message body.', 'webdigitech-woo-product-emailer')
		);

		self::render_input_row(
			'wdt-wcpe-last-test-email-sent-at',
			__('Last test email timestamp', 'webdigitech-woo-product-emailer'),
			$last_test_email_sent_at !== '' ? $last_test_email_sent_at : __('No test email has been recorded yet.', 'webdigitech-woo-product-emailer'),
			'text',
			__('Updated automatically after a successful send.', 'webdigitech-woo-product-emailer'),
			true
		);

		self::render_textarea_row(
			'wdt-wcpe-last-test-email-error',
			__('Last stored test email error', 'webdigitech-woo-product-emailer'),
			$last_test_email_error !== '' ? $last_test_email_error : '',
			3,
			__('If the send fails, the latest error message will appear here.', 'webdigitech-woo-product-emailer'),
			true
		);

		echo '</tbody></table>';

		echo '<p style="margin:16px 0 0;">';
		echo '<button type="button" id="wdt-wcpe-send-test-email" class="button button-primary" ' . disabled(! $has_ajax_test_email_handler, true, false) . '>';
		echo esc_html__('Send Test Email', 'webdigitech-woo-product-emailer');
		echo '</button>';

		if ($has_ajax_test_email_handler) {
			echo '<span style="margin-left:10px;color:#50575e;">' . esc_html__('The request will be sent through admin AJAX without reloading the page.', 'webdigitech-woo-product-emailer') . '</span>';
		} else {
			echo '<span style="margin-left:10px;color:#50575e;">' . esc_html__('The button remains disabled until the AJAX handler is available.', 'webdigitech-woo-product-emailer') . '</span>';
		}

		echo '</p>';
		echo '</div>';
	}

	/**
	 * Рендерира runtime summary секцията.
	 *
	 * @param string               $allowed_order_statuses_label Етикет за order statuses.
	 * @param array<string, mixed> $settings Настройки.
	 * @return void
	 */
	private static function render_runtime_summary_section(
		string $allowed_order_statuses_label,
		array $settings
	): void {
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Runtime Summary', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<table class="widefat striped"><tbody>';

		self::render_summary_row(__('Allowed order statuses', 'webdigitech-woo-product-emailer'), $allowed_order_statuses_label);
		self::render_summary_row(__('Content type mode', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'mail_content_type_mode', Constants::DEFAULT_MAIL_CONTENT_TYPE));
		self::render_summary_row(__('Sender name override', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'sender_name_override', '—'));
		self::render_summary_row(__('Sender email override', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'sender_email_override', '—'));
		self::render_summary_row(__('Retry max attempts', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'retry_max_attempts', '3'));
		self::render_summary_row(__('Retry batch size', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'retry_batch_size', '25'));
		self::render_summary_row(__('Recovery lookback days', 'webdigitech-woo-product-emailer'), (string) Helpers::array_get($settings, 'recovery_scan_lookback_days', '7'));

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Рендерира manual diagnostics checklist.
	 *
	 * @param bool   $is_plugin_enabled Плъгинът е активен.
	 * @param bool   $is_fallback_enabled Fallback е активен.
	 * @param bool   $has_valid_fallback_template Има валиден fallback template.
	 * @param string $test_email_recipient Получател.
	 * @param bool   $has_ajax_test_email_handler Има AJAX handler.
	 * @return void
	 */
	private static function render_manual_diagnostics_section(
		bool $is_plugin_enabled,
		bool $is_fallback_enabled,
		bool $has_valid_fallback_template,
		string $test_email_recipient,
		bool $has_ajax_test_email_handler
	): void {
		$items = array(
			array(
				'ok'   => $is_plugin_enabled,
				'text' => __('Global plugin processing must be enabled in Settings.', 'webdigitech-woo-product-emailer'),
			),
			array(
				'ok'   => $is_fallback_enabled,
				'text' => __('Fallback email delivery should be enabled if some products will rely on the global template.', 'webdigitech-woo-product-emailer'),
			),
			array(
				'ok'   => $has_valid_fallback_template,
				'text' => __('Fallback subject and at least one fallback body format should be available.', 'webdigitech-woo-product-emailer'),
			),
			array(
				'ok'   => $test_email_recipient !== '',
				'text' => __('A default test email recipient should be configured in Settings.', 'webdigitech-woo-product-emailer'),
			),
			array(
				'ok'   => $has_ajax_test_email_handler,
				'text' => __('The test-email backend handler should be implemented before the test button is enabled.', 'webdigitech-woo-product-emailer'),
			),
		);

		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Manual Diagnostics Checklist', 'webdigitech-woo-product-emailer') . '</h2>';
		echo '<p>' . esc_html__('Before enabling real customer dispatch, verify the following conditions.', 'webdigitech-woo-product-emailer') . '</p>';
		echo '<ul style="margin:0;padding-left:18px;">';

		foreach ($items as $item) {
			echo '<li style="margin:0 0 8px;">';
			echo $item['ok']
				? '<span style="color:#15803d;font-weight:700;">✓</span> '
				: '<span style="color:#b45309;font-weight:700;">•</span> ';
			echo esc_html((string) $item['text']);
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Рендерира input ред.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type Input type.
	 * @param string $description Описание.
	 * @param bool   $readonly Readonly.
	 * @return void
	 */
	private static function render_input_row(
		string $field_id,
		string $label,
		string $value,
		string $type,
		string $description,
		bool $readonly = false
	): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label></th>';
		echo '<td>';
		echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($field_id) . '" class="regular-text" value="' . esc_attr($value) . '" ' . ($readonly ? 'readonly="readonly"' : '') . '>';
		echo '<p class="description">' . esc_html($description) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Рендерира textarea ред.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param int    $rows Rows.
	 * @param string $description Описание.
	 * @param bool   $readonly Readonly.
	 * @return void
	 */
	private static function render_textarea_row(
		string $field_id,
		string $label,
		string $value,
		int $rows,
		string $description,
		bool $readonly = false
	): void {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label></th>';
		echo '<td>';
		echo '<textarea id="' . esc_attr($field_id) . '" class="large-text code" rows="' . esc_attr((string) $rows) . '" ' . ($readonly ? 'readonly="readonly"' : '') . '>' . esc_textarea($value) . '</textarea>';
		echo '<p class="description">' . esc_html($description) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Рендерира summary row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private static function render_summary_row(string $label, string $value): void
	{
		echo '<tr>';
		echo '<th style="width:280px;">' . esc_html($label) . '</th>';
		echo '<td>' . esc_html($value !== '' ? $value : '—') . '</td>';
		echo '</tr>';
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}