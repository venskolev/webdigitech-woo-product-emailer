<?php
/**
 * Mail transport слой за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

use WP_Error;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Изпраща имейлите през wp_mail с контролирани headers и content type.
 *
 * Този клас трябва да бъде само transport слой.
 * Не трябва да съдържа test-email orchestration, template selection,
 * placeholder resolving или запис в option storage за test flow.
 */
final class Mailer
{
	/**
	 * Изпраща имейл през wp_mail().
	 *
	 * @param array<string, mixed> $payload Данни за имейла.
	 * @return array<string, mixed>
	 */
	public static function send(array $payload): array
	{
		$to          = Helpers::sanitize_email_address((string) Helpers::array_get($payload, 'to', ''));
		$subject     = Helpers::sanitize_email_subject((string) Helpers::array_get($payload, 'subject', ''));
		$body_html   = Helpers::sanitize_template_html((string) Helpers::array_get($payload, 'body_html', ''));
		$body_text   = Helpers::sanitize_template_text((string) Helpers::array_get($payload, 'body_text', ''));
		$headers     = self::build_headers();
		$content_type = Helpers::get_mail_content_type();

		if ($to === '') {
			return self::failed_result(
				'Invalid recipient email address.',
				array(
					'to' => '',
				)
			);
		}

		if ($subject === '') {
			return self::failed_result(
				'Email subject is empty.',
				array(
					'to' => $to,
				)
			);
		}

		$body = self::resolve_body_by_content_type($content_type, $body_html, $body_text);

		if ($body === '') {
			return self::failed_result(
				'Email body is empty.',
				array(
					'to'           => $to,
					'content_type' => $content_type,
				)
			);
		}

		$content_type_filter = static function () use ($content_type): string {
			return $content_type;
		};

		$wp_mail_failed_error = '';
		$wp_mail_failed_data  = array();

		$wp_mail_failed_handler = static function (WP_Error $error) use (&$wp_mail_failed_error, &$wp_mail_failed_data): void {
			$wp_mail_failed_error = trim((string) $error->get_error_message());

			$error_data = $error->get_error_data();

			if (is_array($error_data)) {
				$wp_mail_failed_data = $error_data;
			}
		};

		add_filter('wp_mail_content_type', $content_type_filter);
		add_action('wp_mail_failed', $wp_mail_failed_handler, 10, 1);

		try {
			Logger::log_debug(
				'Attempting to send email through wp_mail().',
				array(
					'to'           => $to,
					'subject'      => $subject,
					'content_type' => $content_type,
					'headers'      => $headers,
				)
			);

			$sent = wp_mail($to, $subject, $body, $headers);

			if (! $sent) {
				$error_message = $wp_mail_failed_error !== ''
					? $wp_mail_failed_error
					: 'wp_mail() returned false.';

				return self::failed_result(
					$error_message,
					array(
						'to'                  => $to,
						'subject'             => $subject,
						'content_type'        => $content_type,
						'headers'             => $headers,
						'wp_mail_failed_data' => $wp_mail_failed_data,
					)
				);
			}

			if ($wp_mail_failed_error !== '') {
				return self::failed_result(
					$wp_mail_failed_error,
					array(
						'to'                  => $to,
						'subject'             => $subject,
						'content_type'        => $content_type,
						'headers'             => $headers,
						'wp_mail_failed_data' => $wp_mail_failed_data,
					)
				);
			}

			Logger::log_debug(
				'wp_mail() accepted the email for sending.',
				array(
					'to'           => $to,
					'subject'      => $subject,
					'content_type' => $content_type,
				)
			);

			return array(
				'success'      => true,
				'error'        => '',
				'to'           => $to,
				'subject'      => $subject,
				'content_type' => $content_type,
				'headers'      => $headers,
				'body'         => $body,
			);
		} catch (\Throwable $exception) {
			Logger::log_error(
				'Mailer transport exception while sending email.',
				array(
					'to'           => $to,
					'subject'      => $subject,
					'content_type' => $content_type,
					'headers'      => $headers,
					'error'        => $exception->getMessage(),
					'trace'        => $exception->getTraceAsString(),
				)
			);

			return self::failed_result(
				$exception->getMessage(),
				array(
					'to'           => $to,
					'subject'      => $subject,
					'content_type' => $content_type,
					'headers'      => $headers,
				)
			);
		} finally {
			remove_filter('wp_mail_content_type', $content_type_filter);
			remove_action('wp_mail_failed', $wp_mail_failed_handler, 10);
		}
	}

	/**
	 * Изгражда mail headers.
	 *
	 * @return string[]
	 */
	private static function build_headers(): array
	{
		$headers      = array();
		$sender_name  = Helpers::get_sender_name_override();
		$sender_email = Helpers::get_sender_email_override();

		if ($sender_name !== '' && $sender_email !== '') {
			$headers[] = sprintf(
				'From: %s <%s>',
				self::sanitize_header_name($sender_name),
				$sender_email
			);
		} elseif ($sender_email !== '') {
			$headers[] = sprintf(
				'From: <%s>',
				$sender_email
			);
		}

		return $headers;
	}

	/**
	 * Определя тялото на имейла според content type.
	 *
	 * @param string $content_type Content type.
	 * @param string $body_html HTML тяло.
	 * @param string $body_text Text тяло.
	 * @return string
	 */
	private static function resolve_body_by_content_type(string $content_type, string $body_html, string $body_text): string
	{
		if ($content_type === 'text/plain') {
			if ($body_text !== '') {
				return $body_text;
			}

			if ($body_html !== '') {
				return Helpers::html_to_text($body_html);
			}

			return '';
		}

		if ($body_html !== '') {
			return $body_html;
		}

		if ($body_text !== '') {
			return nl2br(esc_html($body_text));
		}

		return '';
	}

	/**
	 * Подготвя failed result payload.
	 *
	 * @param string               $error Error message.
	 * @param array<string, mixed> $context Допълнителен контекст.
	 * @return array<string, mixed>
	 */
	private static function failed_result(string $error, array $context = array()): array
	{
		$result = array_merge(
			array(
				'success' => false,
				'error'   => Helpers::sanitize_textarea($error),
			),
			$context
		);

		Logger::log_error(
			'Mailer transport failed.',
			array(
				'error'   => (string) $result['error'],
				'context' => $context,
			)
		);

		return $result;
	}

	/**
	 * Санитизира display name за mail header.
	 *
	 * @param string $name Header name.
	 * @return string
	 */
	private static function sanitize_header_name(string $name): string
	{
		$name = wp_strip_all_tags($name, true);
		$name = str_replace(array("\r", "\n"), ' ', $name);
		$name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

		return $name;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}