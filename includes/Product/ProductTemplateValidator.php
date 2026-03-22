<?php
/**
 * Валидира product-specific email template данните за WooCommerce продукти.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Product;

use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Валидатор за product email template meta данните.
 *
 * Този клас:
 * - валидира meta стойности за конкретен продукт;
 * - нормализира статуси и грешки в стандартизиран вид;
 * - не рендерира UI и не записва meta;
 * - служи като централен слой за проверка на template completeness.
 */
final class ProductTemplateValidator
{
	/**
	 * Валидира template данните по product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function validate_product(int $product_id): array
	{
		if ($product_id <= 0) {
			return self::result(
				false,
				array('Invalid product ID.'),
				array(),
				array(
					'product_id' => $product_id,
				)
			);
		}

		$data = self::get_product_template_data($product_id);

		return self::validate_data($data, $product_id);
	}

	/**
	 * Валидира template данните по подаден асоциативен масив.
	 *
	 * @param array<string, mixed> $data Template data.
	 * @param int                  $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function validate_data(array $data, int $product_id = 0): array
	{
		$errors = array();
		$warnings = array();

		$normalized = self::normalize_template_data($data);

		$is_custom_enabled = $normalized['enable_custom_email'] === 'yes';

		if (! $is_custom_enabled) {
			$warnings[] = 'Custom product email is disabled. Global fallback may be used instead.';

			return self::result(
				true,
				array(),
				$warnings,
				array(
					'product_id'           => $product_id,
					'is_custom_enabled'    => false,
					'is_template_complete' => false,
					'normalized_template'  => $normalized,
					'template_mode'        => 'fallback',
				)
			);
		}

		if ($normalized['subject'] === '') {
			$errors[] = 'Email subject is required.';
		}

		if ($normalized['body_html'] === '' && $normalized['body_text'] === '') {
			$errors[] = 'At least one email body is required: HTML body or text body.';
		}

		if ($normalized['body_html'] !== '' && ! self::looks_like_html_content($normalized['body_html'])) {
			$warnings[] = 'HTML body does not appear to contain HTML markup. This is allowed, but should be reviewed.';
		}

		if ($normalized['heading'] === '' && $normalized['body_html'] !== '') {
			$warnings[] = 'Email heading is empty. The template can still work, but a heading is recommended.';
		}

		if (
			$normalized['subject'] !== '' &&
			self::contains_unsupported_placeholder($normalized['subject'])
		) {
			$warnings[] = 'Email subject contains placeholders that may not be supported.';
		}

		if (
			$normalized['heading'] !== '' &&
			self::contains_unsupported_placeholder($normalized['heading'])
		) {
			$warnings[] = 'Email heading contains placeholders that may not be supported.';
		}

		if (
			$normalized['body_html'] !== '' &&
			self::contains_unsupported_placeholder($normalized['body_html'])
		) {
			$warnings[] = 'HTML body contains placeholders that may not be supported.';
		}

		if (
			$normalized['body_text'] !== '' &&
			self::contains_unsupported_placeholder($normalized['body_text'])
		) {
			$warnings[] = 'Text body contains placeholders that may not be supported.';
		}

		$is_valid = count($errors) === 0;

		self::log_debug(
			'Product template validation completed.',
			array(
				'product_id'          => $product_id,
				'is_valid'            => $is_valid,
				'errors_count'        => count($errors),
				'warnings_count'      => count($warnings),
				'is_custom_enabled'   => $is_custom_enabled,
				'has_subject'         => $normalized['subject'] !== '',
				'has_body_html'       => $normalized['body_html'] !== '',
				'has_body_text'       => $normalized['body_text'] !== '',
			)
		);

		return self::result(
			$is_valid,
			$errors,
			$warnings,
			array(
				'product_id'           => $product_id,
				'is_custom_enabled'    => $is_custom_enabled,
				'is_template_complete' => $is_valid,
				'normalized_template'  => $normalized,
				'template_mode'        => 'product',
			)
		);
	}

	/**
	 * Проверява дали шаблонът е complete и валиден.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_valid_product_template(int $product_id): bool
	{
		$result = self::validate_product($product_id);

		return (bool) ($result['ok'] ?? false);
	}

	/**
	 * Връща template data от post meta.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function get_product_template_data(int $product_id): array
	{
		if ($product_id <= 0) {
			return self::normalize_template_data(array());
		}

		return self::normalize_template_data(
			array(
				'enable_custom_email' => get_post_meta($product_id, Constants::META_ENABLE_CUSTOM_EMAIL, true),
				'subject'             => get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_SUBJECT, true),
				'heading'             => get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_HEADING, true),
				'body_html'           => get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_BODY_HTML, true),
				'body_text'           => get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_BODY_TEXT, true),
				'notes'               => get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_NOTES, true),
			)
		);
	}

	/**
	 * Нормализира template data масива.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, string>
	 */
	private static function normalize_template_data(array $data): array
	{
		return array(
			'enable_custom_email' => Helpers::sanitize_yes_no(
				Helpers::array_get($data, 'enable_custom_email', 'no'),
				'no'
			),
			'subject'             => Helpers::sanitize_email_subject(
				Helpers::array_get($data, 'subject', '')
			),
			'heading'             => Helpers::sanitize_text(
				Helpers::array_get($data, 'heading', '')
			),
			'body_html'           => Helpers::sanitize_template_html(
				Helpers::array_get($data, 'body_html', '')
			),
			'body_text'           => Helpers::sanitize_template_text(
				Helpers::array_get($data, 'body_text', '')
			),
			'notes'               => Helpers::sanitize_textarea(
				Helpers::array_get($data, 'notes', '')
			),
		);
	}

	/**
	 * Проверява дали подаденият текст изглежда като HTML.
	 *
	 * @param string $value Стойност.
	 * @return bool
	 */
	private static function looks_like_html_content(string $value): bool
	{
		$value = trim($value);

		if ($value === '') {
			return false;
		}

		return preg_match('/<[^>]+>/', $value) === 1;
	}

	/**
	 * Проверява дали текстът съдържа placeholders извън позволения списък.
	 *
	 * @param string $value Стойност.
	 * @return bool
	 */
	private static function contains_unsupported_placeholder(string $value): bool
	{
		$placeholders = self::extract_placeholders($value);

		if ($placeholders === array()) {
			return false;
		}

		$allowed = self::allowed_placeholders();

		foreach ($placeholders as $placeholder) {
			if (! in_array($placeholder, $allowed, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Извлича placeholders от текст.
	 *
	 * @param string $value Текст.
	 * @return array<int, string>
	 */
	private static function extract_placeholders(string $value): array
	{
		if ($value === '') {
			return array();
		}

		preg_match_all('/\{[a-zA-Z0-9_\-]+\}/', $value, $matches);

		if (! isset($matches[0]) || ! is_array($matches[0])) {
			return array();
		}

		$placeholders = array();

		foreach ($matches[0] as $match) {
			if (is_string($match) && $match !== '') {
				$placeholders[] = $match;
			}
		}

		return array_values(array_unique($placeholders));
	}

	/**
	 * Позволените placeholders за продукта.
	 *
	 * @return array<int, string>
	 */
	private static function allowed_placeholders(): array
	{
		return array(
			'{customer_email}',
			'{customer_first_name}',
			'{customer_last_name}',
			'{customer_full_name}',
			'{order_id}',
			'{order_number}',
			'{order_status}',
			'{product_name}',
			'{product_sku}',
			'{product_id}',
			'{site_name}',
		);
	}

	/**
	 * Стандартизира резултата на валидатора.
	 *
	 * @param bool               $ok Валиден ли е шаблонът.
	 * @param array<int, string> $errors Грешки.
	 * @param array<int, string> $warnings Предупреждения.
	 * @param array<string, mixed> $meta Допълнителни meta данни.
	 * @return array<string, mixed>
	 */
	private static function result(bool $ok, array $errors, array $warnings, array $meta): array
	{
		return array(
			'ok'       => $ok,
			'errors'   => $errors,
			'warnings' => $warnings,
			'meta'     => $meta,
		);
	}

	/**
	 * Safe debug лог.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	private static function log_debug(string $message, array $context = array()): void
	{
		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Logger')) {
			Logger::log_debug($message, $context);
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}