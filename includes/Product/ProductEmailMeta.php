<?php
/**
 * Записва custom product email meta данните за WooCommerce продукти.
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
 * Отговаря само за запис/изтриване на product email meta полетата.
 *
 * Този клас:
 * - валидира nonce/capability/autosave/revision проверки;
 * - sanitize-ва и записва custom email meta стойностите;
 * - изчиства meta полета при празни стойности;
 * - премахва legacy meta за стария втори checkbox;
 * - НЕ рендерира UI (това е работа на ProductEmailFields).
 */
final class ProductEmailMeta
{
	/**
	 * Регистрира hooks за запис на product email meta.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		if (! is_admin()) {
			return;
		}

		if (! class_exists('\\WooCommerce')) {
			return;
		}

		add_action('woocommerce_admin_process_product_object', array(__CLASS__, 'save_product_object'), 20, 1);
	}

	/**
	 * Записва meta данните през WooCommerce product object lifecycle.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public static function save_product_object(\WC_Product $product): void
	{
		$product_id = (int) $product->get_id();

		if ($product_id <= 0) {
			self::log_debug(
				'ProductEmailMeta skipped save because product ID is invalid.',
				array(
					'product_id' => $product_id,
				)
			);

			return;
		}

		if (! self::can_save($product_id)) {
			self::log_debug(
				'ProductEmailMeta save blocked by guard checks.',
				array(
					'product_id' => $product_id,
				)
			);

			return;
		}

		$data = self::collect_input_data();

		self::persist_field(
			$product_id,
			Constants::META_ENABLE_CUSTOM_EMAIL,
			Helpers::sanitize_yes_no($data[Constants::META_ENABLE_CUSTOM_EMAIL], 'no')
		);

		self::persist_field(
			$product_id,
			Constants::META_PRODUCT_EMAIL_SUBJECT,
			Helpers::sanitize_email_subject($data[Constants::META_PRODUCT_EMAIL_SUBJECT])
		);

		self::persist_field(
			$product_id,
			Constants::META_PRODUCT_EMAIL_HEADING,
			Helpers::sanitize_text($data[Constants::META_PRODUCT_EMAIL_HEADING])
		);

		self::persist_field(
			$product_id,
			Constants::META_PRODUCT_EMAIL_BODY_HTML,
			Helpers::sanitize_template_html($data[Constants::META_PRODUCT_EMAIL_BODY_HTML])
		);

		self::persist_field(
			$product_id,
			Constants::META_PRODUCT_EMAIL_BODY_TEXT,
			Helpers::sanitize_template_text($data[Constants::META_PRODUCT_EMAIL_BODY_TEXT])
		);

		self::persist_field(
			$product_id,
			Constants::META_PRODUCT_EMAIL_NOTES,
			Helpers::sanitize_textarea($data[Constants::META_PRODUCT_EMAIL_NOTES])
		);

		self::delete_legacy_meta($product_id);

		self::log_debug(
			'ProductEmailMeta saved successfully.',
			array(
				'product_id'          => $product_id,
				'enable_custom_email' => Helpers::sanitize_yes_no($data[Constants::META_ENABLE_CUSTOM_EMAIL], 'no'),
				'has_subject'         => trim((string) $data[Constants::META_PRODUCT_EMAIL_SUBJECT]) !== '',
				'has_heading'         => trim((string) $data[Constants::META_PRODUCT_EMAIL_HEADING]) !== '',
				'has_body_html'       => trim((string) $data[Constants::META_PRODUCT_EMAIL_BODY_HTML]) !== '',
				'has_body_text'       => trim((string) $data[Constants::META_PRODUCT_EMAIL_BODY_TEXT]) !== '',
				'has_internal_notes'  => trim((string) $data[Constants::META_PRODUCT_EMAIL_NOTES]) !== '',
				'legacy_meta_removed' => true,
			)
		);
	}

	/**
	 * Проверява дали текущият request е позволен за запис.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function can_save(int $product_id): bool
	{
		if ($product_id <= 0) {
			return false;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return false;
		}

		if (wp_is_post_autosave($product_id)) {
			return false;
		}

		if (wp_is_post_revision($product_id)) {
			return false;
		}

		if (! current_user_can('edit_post', $product_id)) {
			return false;
		}

		$nonce = isset($_POST['wdt_wcpe_product_email_nonce'])
			? sanitize_text_field(wp_unslash((string) $_POST['wdt_wcpe_product_email_nonce']))
			: '';

		if ($nonce === '') {
			return false;
		}

		return wp_verify_nonce($nonce, 'wdt_wcpe_save_product_email') === 1;
	}

	/**
	 * Събира входните стойности от POST заявката.
	 *
	 * Checkbox полетата се нормализират ръчно, защото при unchecked не присъстват в POST.
	 *
	 * @return array<string, string>
	 */
	private static function collect_input_data(): array
	{
		return array(
			Constants::META_ENABLE_CUSTOM_EMAIL     => self::read_checkbox(Constants::META_ENABLE_CUSTOM_EMAIL, 'no'),
			Constants::META_PRODUCT_EMAIL_SUBJECT   => self::read_text(Constants::META_PRODUCT_EMAIL_SUBJECT),
			Constants::META_PRODUCT_EMAIL_HEADING   => self::read_text(Constants::META_PRODUCT_EMAIL_HEADING),
			Constants::META_PRODUCT_EMAIL_BODY_HTML => self::read_raw_textarea(Constants::META_PRODUCT_EMAIL_BODY_HTML),
			Constants::META_PRODUCT_EMAIL_BODY_TEXT => self::read_raw_textarea(Constants::META_PRODUCT_EMAIL_BODY_TEXT),
			Constants::META_PRODUCT_EMAIL_NOTES     => self::read_raw_textarea(Constants::META_PRODUCT_EMAIL_NOTES),
		);
	}

	/**
	 * Чете checkbox поле от POST.
	 *
	 * @param string $key Field key.
	 * @param string $default Default yes/no value.
	 * @return string
	 */
	private static function read_checkbox(string $key, string $default = 'no'): string
	{
		if (! isset($_POST[$key])) {
			return $default === 'yes' ? 'yes' : 'no';
		}

		$value = sanitize_text_field(wp_unslash((string) $_POST[$key]));

		return $value === 'yes' ? 'yes' : 'no';
	}

	/**
	 * Чете обикновено text поле от POST.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function read_text(string $key): string
	{
		if (! isset($_POST[$key])) {
			return '';
		}

		return sanitize_text_field(wp_unslash((string) $_POST[$key]));
	}

	/**
	 * Чете raw textarea / wp_editor съдържание от POST.
	 *
	 * Тук не sanitize-ваме директно, защото после минава през централизирани Helpers.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function read_raw_textarea(string $key): string
	{
		if (! isset($_POST[$key])) {
			return '';
		}

		return (string) wp_unslash($_POST[$key]);
	}

	/**
	 * Записва или изтрива meta поле според стойността.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $meta_key Meta key.
	 * @param string $value Sanitized value.
	 * @return void
	 */
	private static function persist_field(int $product_id, string $meta_key, string $value): void
	{
		$value = trim($value);

		if ($value === '') {
			delete_post_meta($product_id, $meta_key);

			return;
		}

		update_post_meta($product_id, $meta_key, $value);
	}

	/**
	 * Премахва legacy meta полето от стария втори checkbox.
	 *
	 * Това предотвратява стари стойности да влияят на resolver логиката,
	 * ако някъде все още се чете този ключ.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private static function delete_legacy_meta(int $product_id): void
	{
		if ($product_id <= 0) {
			return;
		}

		delete_post_meta($product_id, Constants::META_PRODUCT_EMAIL_ENABLED);
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