<?php
/**
 * AJAX handler за test email от Tools страницата.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\API;

use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;
use WebDigiTech\WooProductEmailer\OrderEmailProcessor;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Обработва AJAX заявка за тестово изпращане на email.
 */
final class AjaxTestEmail
{
	/**
	 * Регистрира AJAX hooks.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_action('wp_ajax_' . Constants::AJAX_ACTION_TEST_EMAIL, array(__CLASS__, 'handle'));
	}

	/**
	 * Главен AJAX handler.
	 *
	 * @return void
	 */
	public static function handle(): void
	{
		Logger::log_debug(
			'AjaxTestEmail::handle() entered.',
			array(
				'action'        => Constants::AJAX_ACTION_TEST_EMAIL,
				'current_user'  => get_current_user_id(),
				'has_nonce'     => isset($_POST['nonce']),
				'has_recipient' => isset($_POST['recipient']),
				'has_subject'   => isset($_POST['subject']),
				'has_heading'   => isset($_POST['heading']),
				'has_text_body' => isset($_POST['text_body']),
				'has_html_body' => isset($_POST['html_body']),
			)
		);

		if (! Capabilities::current_user_can_manage()) {
			Logger::log_error(
				'Test email AJAX denied due to missing capability.',
				array(
					'action'       => Constants::AJAX_ACTION_TEST_EMAIL,
					'current_user' => get_current_user_id(),
				)
			);

			self::json_error(
				array(
					'message' => __('You are not allowed to perform this action.', 'webdigitech-woo-product-emailer'),
				),
				403
			);
		}

		check_ajax_referer(Constants::AJAX_ACTION_TEST_EMAIL, 'nonce');

		Logger::log_debug(
			'Test email AJAX nonce validated successfully.',
			array(
				'action'       => Constants::AJAX_ACTION_TEST_EMAIL,
				'current_user' => get_current_user_id(),
			)
		);

		$settings = Helpers::get_settings();

		if (! Helpers::is_yes(Helpers::array_get($settings, 'plugin_enabled', 'yes'))) {
			$message = __('The plugin is currently disabled in settings.', 'webdigitech-woo-product-emailer');

			self::store_test_email_error($message);

			Logger::log_error(
				'Test email AJAX aborted because plugin is disabled.',
				array(
					'action'       => Constants::AJAX_ACTION_TEST_EMAIL,
					'current_user' => get_current_user_id(),
				)
			);

			self::json_error(
				array(
					'message' => $message,
				),
				400
			);
		}

		$recipient = self::resolve_recipient();
		$payload   = self::build_test_email_payload($settings, $recipient);

		Logger::log_debug(
			'Test email AJAX payload resolved for centralized processor.',
			array(
				'action'          => Constants::AJAX_ACTION_TEST_EMAIL,
				'current_user'    => get_current_user_id(),
				'recipient'       => $recipient,
				'subject_present' => $payload['subject'] !== '',
				'heading_present' => $payload['heading'] !== '',
				'html_present'    => $payload['body_html'] !== '',
				'text_present'    => $payload['body_text'] !== '',
			)
		);

		if ($recipient === '') {
			$message = __('No valid recipient email address was provided.', 'webdigitech-woo-product-emailer');

			self::store_test_email_error($message);

			Logger::log_error(
				'Test email AJAX aborted because recipient is empty.',
				array(
					'action'       => Constants::AJAX_ACTION_TEST_EMAIL,
					'current_user' => get_current_user_id(),
				)
			);

			self::json_error(
				array(
					'message' => $message,
				),
				400
			);
		}

		try {
			/**
			 * Тестовият email вече минава през същия централен pipeline
			 * като редовния dispatch, вместо през отделен mail flow.
			 */
			$result = OrderEmailProcessor::process_test_email(
				$payload,
				Constants::TRIGGER_MANUAL_TEST
			);

			$status          = Helpers::sanitize_text((string) Helpers::array_get($result, 'status', ''));
			$reason          = Helpers::sanitize_textarea((string) Helpers::array_get($result, 'reason', ''));
			$template_source = Helpers::sanitize_text((string) Helpers::array_get($result, 'template_source', 'test_email'));
			$content_type    = self::resolve_content_type($settings);

			Logger::log_debug(
				'Test email AJAX centralized processor returned.',
				array(
					'action'          => Constants::AJAX_ACTION_TEST_EMAIL,
					'current_user'    => get_current_user_id(),
					'recipient'       => $recipient,
					'status'          => $status,
					'reason'          => $reason,
					'template_source' => $template_source,
					'content_type'    => $content_type,
				)
			);

			if ($status !== Constants::STATUS_SUCCESS) {
				$error_message = $reason !== ''
					? $reason
					: __('The test email could not be sent.', 'webdigitech-woo-product-emailer');

				self::store_test_email_error($error_message);

				Logger::log_error(
					'Centralized test email dispatch failed.',
					array(
						'channel'         => 'ajax_test_email',
						'recipient'       => $recipient,
						'status'          => $status,
						'reason'          => $reason,
						'template_source' => $template_source,
						'content_type'    => $content_type,
						'current_user'    => get_current_user_id(),
					)
				);

				self::json_error(
					array(
						'message'         => $error_message,
						'recipient'       => $recipient,
						'content_type'    => $content_type,
						'subject_preview' => (string) Helpers::array_get($payload, 'subject', ''),
						'last_error'      => $error_message,
						'status'          => $status,
						'reason'          => $reason,
					),
					500
				);
			}

			$sent_at = (string) get_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, '');

			Logger::log_info(
				'Test email sent successfully through centralized dispatch pipeline.',
				array(
					'channel'         => 'ajax_test_email',
					'recipient'       => $recipient,
					'status'          => $status,
					'template_source' => $template_source,
					'content_type'    => $content_type,
					'current_user'    => get_current_user_id(),
				)
			);

			self::json_success(
				array(
					'message'         => __('Test email sent successfully.', 'webdigitech-woo-product-emailer'),
					'recipient'       => $recipient,
					'sent_at'         => $sent_at,
					'content_type'    => $content_type,
					'subject_preview' => (string) Helpers::array_get($payload, 'subject', ''),
					'last_error'      => '',
					'status'          => $status,
					'reason'          => 'sent',
				)
			);
		} catch (\Throwable $throwable) {
			$error_message = sprintf(
				/* translators: %s: exception message */
				__('Test email failed: %s', 'webdigitech-woo-product-emailer'),
				$throwable->getMessage()
			);

			self::store_test_email_error($error_message);

			Logger::log_error(
				$error_message,
				array(
					'channel'      => 'ajax_test_email',
					'recipient'    => $recipient,
					'content_type' => self::resolve_content_type($settings),
					'current_user' => get_current_user_id(),
					'exception'    => get_class($throwable),
					'trace'        => $throwable->getTraceAsString(),
				)
			);

			self::json_error(
				array(
					'message' => $error_message,
				),
				500
			);
		}
	}

	/**
	 * Разрешава адреса на получателя.
	 *
	 * @return string
	 */
	private static function resolve_recipient(): string
	{
		$posted_recipient = '';

		if (isset($_POST['recipient'])) {
			$posted_recipient = Helpers::sanitize_email_address(wp_unslash($_POST['recipient']));
		}

		if ($posted_recipient !== '') {
			return $posted_recipient;
		}

		$settings = Helpers::get_settings();

		return Helpers::sanitize_email_address(
			(string) Helpers::array_get($settings, 'test_email_recipient', '')
		);
	}

	/**
	 * Сглобява payload от POST + fallback settings за общия processor.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @param string               $recipient Получател.
	 * @return array<string, string>
	 */
	private static function build_test_email_payload(array $settings, string $recipient): array
	{
		$posted_subject = isset($_POST['subject'])
			? Helpers::sanitize_email_subject(wp_unslash($_POST['subject']))
			: '';

		$posted_heading = isset($_POST['heading'])
			? Helpers::sanitize_text(wp_unslash($_POST['heading']))
			: '';

		$posted_html_body = isset($_POST['html_body'])
			? Helpers::sanitize_template_html(wp_unslash($_POST['html_body']))
			: '';

		$posted_text_body = isset($_POST['text_body'])
			? Helpers::sanitize_template_text(wp_unslash($_POST['text_body']))
			: '';

		$fallback_subject   = (string) Helpers::array_get($settings, 'fallback_email_subject', '');
		$fallback_heading   = (string) Helpers::array_get($settings, 'fallback_email_heading', '');
		$fallback_html_body = (string) Helpers::array_get($settings, 'fallback_email_body_html', '');
		$fallback_text_body = (string) Helpers::array_get($settings, 'fallback_email_body_text', '');

		$subject = $posted_subject !== ''
			? $posted_subject
			: $fallback_subject;

		if ($subject === '') {
			$subject = __('Test email from Product Email plugin', 'webdigitech-woo-product-emailer');
		}

		$heading = $posted_heading !== ''
			? $posted_heading
			: $fallback_heading;

		if ($heading === '') {
			$heading = __('Test Email', 'webdigitech-woo-product-emailer');
		}

		$body_html = $posted_html_body !== ''
			? $posted_html_body
			: $fallback_html_body;

		$body_text = $posted_text_body !== ''
			? $posted_text_body
			: $fallback_text_body;

		if ($body_html === '' && $body_text === '') {
			$body_text = __('This is a diagnostic test email generated from the plugin tools page.', 'webdigitech-woo-product-emailer');
		}

		if ($body_text === '' && $body_html !== '') {
			$body_text = Helpers::html_to_text($body_html);
		}

		return array(
			'to'                  => $recipient,
			'subject'             => $subject,
			'heading'             => $heading,
			'body_html'           => $body_html,
			'body_text'           => $body_text,
			'customer_first_name' => __('Test', 'webdigitech-woo-product-emailer'),
			'customer_last_name'  => __('User', 'webdigitech-woo-product-emailer'),
			'order_id'            => '999999',
			'order_number'        => '999999',
			'order_date'          => wp_date(get_option('date_format') . ' ' . get_option('time_format')),
			'product_id'          => '999999',
			'product_name'        => __('Test Product', 'webdigitech-woo-product-emailer'),
			'product_sku'         => 'TEST-SKU',
		);
	}

	/**
	 * Определя content type-а.
	 *
	 * Използва се за debug/response контекст.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return string
	 */
	private static function resolve_content_type(array $settings): string
	{
		$content_type = (string) Helpers::array_get(
			$settings,
			'mail_content_type_mode',
			Constants::DEFAULT_MAIL_CONTENT_TYPE
		);

		if (! in_array($content_type, Constants::ALLOWED_MAIL_CONTENT_TYPES, true)) {
			return Constants::DEFAULT_MAIL_CONTENT_TYPE;
		}

		return $content_type;
	}

	/**
	 * Записва последната test email грешка в option storage.
	 *
	 * @param string $message Съобщение.
	 * @return void
	 */
	private static function store_test_email_error(string $message): void
	{
		update_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, $message, false);
	}

	/**
	 * JSON success response.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return void
	 */
	private static function json_success(array $data): void
	{
		wp_send_json_success($data, 200);
	}

	/**
	 * JSON error response.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @param int                  $status_code HTTP status.
	 * @return void
	 */
	private static function json_error(array $data, int $status_code = 400): void
	{
		wp_send_json_error($data, $status_code);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}