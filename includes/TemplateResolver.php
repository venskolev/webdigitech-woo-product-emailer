<?php
/**
 * Resolver за избор на имейл шаблон.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Избира правилния шаблон за конкретен продукт/item.
 */
final class TemplateResolver
{
	/**
	 * Връща шаблона за конкретен продукт.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_product(int $product_id, int $variation_id = 0): array
	{
		$product_id   = max(0, $product_id);
		$variation_id = max(0, $variation_id);

		if ($product_id <= 0) {
			return self::invalid_result(
				'Invalid product ID.',
				'none',
				'',
				0,
				$variation_id
			);
		}

		$product_template = self::get_product_template($product_id, $variation_id);

		if ((bool) $product_template['is_enabled'] && (bool) $product_template['is_valid']) {
			return array(
				'is_valid'            => true,
				'source'              => Constants::TEMPLATE_SOURCE_PRODUCT_CUSTOM,
				'template_identifier' => 'product:' . $product_id,
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
				'use_fallback'        => false,
				'subject'             => (string) $product_template['subject'],
				'heading'             => (string) $product_template['heading'],
				'body_html'           => (string) $product_template['body_html'],
				'body_text'           => (string) $product_template['body_text'],
				'error'               => '',
			);
		}

		if (Helpers::has_valid_fallback_template()) {
			$fallback_template = Helpers::get_fallback_template_settings();

			return array(
				'is_valid'            => true,
				'source'              => Constants::TEMPLATE_SOURCE_PLUGIN_FALLBACK,
				'template_identifier' => 'fallback:global',
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
				'use_fallback'        => true,
				'subject'             => (string) $fallback_template['subject'],
				'heading'             => (string) $fallback_template['heading'],
				'body_html'           => (string) $fallback_template['body_html'],
				'body_text'           => (string) $fallback_template['body_text'],
				'error'               => '',
			);
		}

		if ((bool) $product_template['is_enabled'] && ! (bool) $product_template['is_valid']) {
			return self::invalid_result(
				'Product custom email is enabled, but the template is incomplete and no valid fallback template is configured.',
				Constants::TEMPLATE_SOURCE_PRODUCT_CUSTOM,
				'product:' . $product_id,
				$product_id,
				$variation_id
			);
		}

		return self::invalid_result(
			'No valid email template is available for this product and the global fallback template is not configured.',
			'none',
			'',
			$product_id,
			$variation_id
		);
	}

	/**
	 * Връща template за конкретен WooCommerce order item.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_order_item(\WC_Order_Item_Product $item): array
	{
		$product_id   = (int) $item->get_product_id();
		$variation_id = (int) $item->get_variation_id();

		return self::resolve_for_product($product_id, $variation_id);
	}

	/**
	 * Валидира шаблон payload.
	 *
	 * @param array<string, mixed> $template Template данни.
	 * @return bool
	 */
	public static function is_valid_template(array $template): bool
	{
		$subject   = Helpers::sanitize_email_subject(Helpers::array_get($template, 'subject', ''));
		$body_html = Helpers::sanitize_template_html(Helpers::array_get($template, 'body_html', ''));
		$body_text = Helpers::sanitize_template_text(Helpers::array_get($template, 'body_text', ''));

		if ($subject === '') {
			return false;
		}

		return $body_html !== '' || $body_text !== '';
	}

	/**
	 * Взима custom template от product/variation meta.
	 *
	 * При variation:
	 * - първо се гледа variation meta;
	 * - ако там custom template не е активиран, падаме към parent product meta.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 * @return array<string, mixed>
	 */
	private static function get_product_template(int $product_id, int $variation_id = 0): array
	{
		if ($variation_id > 0) {
			$variation_template = self::build_template_payload($variation_id, $product_id, $variation_id);

			if ((bool) $variation_template['is_enabled']) {
				return $variation_template;
			}
		}

		return self::build_template_payload($product_id, $product_id, $variation_id);
	}

	/**
	 * Изгражда template payload от конкретен post ID.
	 *
	 * @param int $target_post_id Post ID, от който четем meta.
	 * @param int $product_id Parent/simple product ID.
	 * @param int $variation_id Variation ID.
	 * @return array<string, mixed>
	 */
	private static function build_template_payload(int $target_post_id, int $product_id, int $variation_id = 0): array
	{
		$is_enabled = Helpers::sanitize_yes_no(
			get_post_meta($target_post_id, Constants::META_ENABLE_CUSTOM_EMAIL, true),
			'no'
		);

		$subject = Helpers::sanitize_email_subject(
			get_post_meta($target_post_id, Constants::META_PRODUCT_EMAIL_SUBJECT, true)
		);

		$heading = Helpers::sanitize_text(
			get_post_meta($target_post_id, Constants::META_PRODUCT_EMAIL_HEADING, true)
		);

		$body_html = Helpers::sanitize_template_html(
			get_post_meta($target_post_id, Constants::META_PRODUCT_EMAIL_BODY_HTML, true)
		);

		$body_text = Helpers::sanitize_template_text(
			get_post_meta($target_post_id, Constants::META_PRODUCT_EMAIL_BODY_TEXT, true)
		);

		if ($body_text === '' && $body_html !== '') {
			$body_text = Helpers::html_to_text($body_html);
		}

		$template = array(
			'is_enabled'   => $is_enabled === 'yes',
			'is_valid'     => false,
			'subject'      => $subject,
			'heading'      => $heading,
			'body_html'    => $body_html,
			'body_text'    => $body_text,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'target_post_id'=> $target_post_id,
		);

		$template['is_valid'] = self::is_valid_template($template);

		return $template;
	}

	/**
	 * Връща invalid result payload.
	 *
	 * @param string $error Error message.
	 * @param string $source Source.
	 * @param string $template_identifier Template identifier.
	 * @param int    $product_id Product ID.
	 * @param int    $variation_id Variation ID.
	 * @return array<string, mixed>
	 */
	private static function invalid_result(
		string $error,
		string $source,
		string $template_identifier,
		int $product_id,
		int $variation_id
	): array {
		return array(
			'is_valid'            => false,
			'source'              => $source,
			'template_identifier' => $template_identifier,
			'product_id'          => $product_id,
			'variation_id'        => $variation_id,
			'use_fallback'        => false,
			'subject'             => '',
			'heading'             => '',
			'body_html'           => '',
			'body_text'           => '',
			'error'               => Helpers::sanitize_textarea($error),
		);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}