<?php
/**
 * Placeholder resolver за имейл шаблоните.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Замества позволените placeholder-и в subject/body шаблоните.
 */
final class PlaceholderResolver
{
	/**
	 * Resolve-ва template масив за конкретен order item контекст.
	 *
	 * @param array<string, mixed>   $template Template данни.
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item WooCommerce order item.
	 * @return array<string, string>
	 */
	public static function resolve_template(
		array $template,
		\WC_Order $order,
		\WC_Order_Item_Product $item
	): array {
		$placeholders = self::build_placeholders($order, $item);

		$subject = self::replace_placeholders(
			Helpers::sanitize_email_subject(Helpers::array_get($template, 'subject', '')),
			$placeholders
		);

		$heading = self::replace_placeholders(
			Helpers::sanitize_text(Helpers::array_get($template, 'heading', '')),
			$placeholders
		);

		$body_html = self::replace_placeholders(
			Helpers::sanitize_template_html(Helpers::array_get($template, 'body_html', '')),
			$placeholders
		);

		$body_text = self::replace_placeholders(
			Helpers::sanitize_template_text(Helpers::array_get($template, 'body_text', '')),
			$placeholders
		);

		if ($body_text === '' && $body_html !== '') {
			$body_text = Helpers::html_to_text($body_html);
		}

		return array(
			'subject'   => $subject,
			'heading'   => $heading,
			'body_html' => $body_html,
			'body_text' => $body_text,
		);
	}

	/**
	 * Resolve-ва единичен текстов шаблон.
	 *
	 * @param string                 $content Шаблон съдържание.
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item WooCommerce order item.
	 * @return string
	 */
	public static function resolve_string(
		string $content,
		\WC_Order $order,
		\WC_Order_Item_Product $item
	): string {
		return self::replace_placeholders($content, self::build_placeholders($order, $item));
	}

	/**
	 * Връща placeholder map за реален order item контекст.
	 *
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item WooCommerce order item.
	 * @return array<string, string>
	 */
	public static function build_placeholders(\WC_Order $order, \WC_Order_Item_Product $item): array
	{
		$product_id = (int) $item->get_product_id();
		$product    = $item->get_product();

		$billing_first_name = Helpers::sanitize_text((string) $order->get_billing_first_name());
		$billing_last_name  = Helpers::sanitize_text((string) $order->get_billing_last_name());
		$customer_email     = Helpers::sanitize_email_address((string) $order->get_billing_email());

		$customer_full_name = trim($billing_first_name . ' ' . $billing_last_name);
		if ($customer_full_name === '') {
			$customer_full_name = $customer_email;
		}

		$order_date           = $order->get_date_created();
		$order_date_formatted = '';

		if ($order_date instanceof \WC_DateTime) {
			$order_date_formatted = wp_date(
				get_option('date_format') . ' ' . get_option('time_format'),
				$order_date->getTimestamp()
			);
		}

		$product_name = Helpers::sanitize_text((string) $item->get_name());
		$product_sku  = '';

		if ($product instanceof \WC_Product) {
			$product_sku = Helpers::sanitize_text((string) $product->get_sku());
		}

		$site_name   = self::get_site_name();
		$site_url    = self::get_site_url();
		$store_name  = self::get_store_name();
		$store_email = self::get_store_email();

		$placeholders = array(
			'{customer_first_name}' => $billing_first_name,
			'{customer_last_name}'  => $billing_last_name,
			'{customer_full_name}'  => $customer_full_name,
			'{customer_email}'      => $customer_email,
			'{order_id}'            => (string) $order->get_id(),
			'{order_number}'        => Helpers::sanitize_text((string) $order->get_order_number()),
			'{order_date}'          => $order_date_formatted,
			'{product_name}'        => $product_name,
			'{product_sku}'         => $product_sku,
			'{product_id}'          => (string) $product_id,
			'{site_name}'           => $site_name,
			'{site_url}'            => $site_url,
			'{store_name}'          => $store_name,
			'{store_email}'         => $store_email,
		);

		return self::filter_allowed_placeholders($placeholders);
	}

	/**
	 * Връща placeholder map за test/synthetic контекст.
	 *
	 * Това ни позволява test email-ът да използва същия resolver pipeline
	 * като реалния send, без паралелен placeholder механизъм.
	 *
	 * @param array<string, mixed> $context Synthetic context.
	 * @return array<string, string>
	 */
	public static function build_test_placeholders(array $context = array()): array
	{
		$customer_first_name = Helpers::sanitize_text((string) Helpers::array_get($context, 'customer_first_name', ''));
		$customer_last_name  = Helpers::sanitize_text((string) Helpers::array_get($context, 'customer_last_name', ''));
		$customer_email      = Helpers::sanitize_email_address((string) Helpers::array_get($context, 'customer_email', ''));
		$order_id            = Helpers::sanitize_text((string) Helpers::array_get($context, 'order_id', '999999'));
		$order_number        = Helpers::sanitize_text((string) Helpers::array_get($context, 'order_number', $order_id));
		$order_date          = Helpers::sanitize_text((string) Helpers::array_get($context, 'order_date', wp_date(get_option('date_format') . ' ' . get_option('time_format'))));
		$product_name        = Helpers::sanitize_text((string) Helpers::array_get($context, 'product_name', esc_html__('Sample Product', 'webdigitech-woo-product-emailer')));
		$product_sku         = Helpers::sanitize_text((string) Helpers::array_get($context, 'product_sku', 'TEST-SKU'));
		$product_id          = Helpers::sanitize_text((string) Helpers::array_get($context, 'product_id', '999999'));

		$customer_full_name = trim($customer_first_name . ' ' . $customer_last_name);
		if ($customer_full_name === '') {
			$customer_full_name = $customer_email !== '' ? $customer_email : esc_html__('Valued Customer', 'webdigitech-woo-product-emailer');
		}

		$site_name   = self::get_site_name();
		$site_url    = self::get_site_url();
		$store_name  = self::get_store_name();
		$store_email = self::get_store_email();

		$placeholders = array(
			'{customer_first_name}' => $customer_first_name,
			'{customer_last_name}'  => $customer_last_name,
			'{customer_full_name}'  => $customer_full_name,
			'{customer_email}'      => $customer_email,
			'{order_id}'            => $order_id,
			'{order_number}'        => $order_number,
			'{order_date}'          => $order_date,
			'{product_name}'        => $product_name,
			'{product_sku}'         => $product_sku,
			'{product_id}'          => $product_id,
			'{site_name}'           => $site_name,
			'{site_url}'            => $site_url,
			'{store_name}'          => $store_name,
			'{store_email}'         => $store_email,
		);

		return self::filter_allowed_placeholders($placeholders);
	}

	/**
	 * Замества позволените placeholder-и в текст.
	 *
	 * Методът е публичен, защото се използва и от test email flow-а,
	 * както и от други общи sender контексти извън директния order/item flow.
	 *
	 * @param string                $content Съдържание.
	 * @param array<string, string> $placeholder_map Placeholder map.
	 * @return string
	 */
	public static function replace_placeholders(string $content, array $placeholder_map): string
	{
		if ($content === '') {
			return '';
		}

		$allowed      = Constants::allowed_placeholders();
		$replace_from = array();
		$replace_to   = array();

		foreach ($allowed as $placeholder) {
			$replace_from[] = $placeholder;
			$replace_to[]   = array_key_exists($placeholder, $placeholder_map)
				? (string) $placeholder_map[$placeholder]
				: '';
		}

		return str_replace($replace_from, $replace_to, $content);
	}

	/**
	 * Филтрира placeholder map само до allowlist-а.
	 *
	 * @param array<string, string> $placeholder_map Placeholder map.
	 * @return array<string, string>
	 */
	private static function filter_allowed_placeholders(array $placeholder_map): array
	{
		$allowed = Constants::allowed_placeholders();
		$result  = array();

		foreach ($allowed as $placeholder) {
			$result[$placeholder] = array_key_exists($placeholder, $placeholder_map)
				? (string) $placeholder_map[$placeholder]
				: '';
		}

		return $result;
	}

	/**
	 * Връща името на сайта.
	 *
	 * @return string
	 */
	private static function get_site_name(): string
	{
		return Helpers::sanitize_text((string) get_bloginfo('name'));
	}

	/**
	 * Връща URL на сайта.
	 *
	 * @return string
	 */
	private static function get_site_url(): string
	{
		return esc_url_raw((string) home_url('/'));
	}

	/**
	 * Връща store name.
	 *
	 * Засега ползваме името на сайта като единен източник на истина.
	 *
	 * @return string
	 */
	private static function get_store_name(): string
	{
		return self::get_site_name();
	}

	/**
	 * Връща store email.
	 *
	 * Засега ползваме admin_email като централен store/contact email.
	 * Това трябва да важи еднакво и за test, и за regular sender-а.
	 *
	 * @return string
	 */
	private static function get_store_email(): string
	{
		$admin_email = Helpers::sanitize_email_address((string) get_option('admin_email', ''));

		return $admin_email;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}