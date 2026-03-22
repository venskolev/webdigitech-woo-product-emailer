<?php
/**
 * Eligibility правила за изпращане на product email-и.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Проверява дали order/item е допустим за изпращане.
 */
final class Eligibility
{
	/**
	 * Проверява order-level допустимост.
	 *
	 * При стандартен status-based flow:
	 * - поръчката трябва да е платена
	 * - статусът трябва да е разрешен
	 *
	 * При Woo hook `woocommerce_payment_complete`:
	 * - платената поръчка е достатъчна
	 * - custom статуси като `pre-ordered` не трябва да блокират изпращането
	 *
	 * @param \WC_Order|null $order WooCommerce order.
	 * @return array<string, mixed>
	 */
	public static function check_order(?\WC_Order $order): array
	{
		if (! Helpers::is_plugin_enabled()) {
			return self::result(false, 'plugin_disabled');
		}

		if (! $order instanceof \WC_Order) {
			return self::result(false, 'invalid_order');
		}

		$billing_email = Helpers::sanitize_email_address((string) $order->get_billing_email());

		if ($billing_email === '') {
			return self::result(false, 'missing_billing_email');
		}

		if (! self::is_paid_order($order)) {
			return self::result(false, 'order_not_paid');
		}

		$status = self::normalize_order_status((string) $order->get_status());

		/**
		 * При payment_complete приемаме платената поръчка за достатъчна.
		 * Това пази съвместимост с custom WooCommerce статуси от външни плъгини
		 * като Tutor, pre-orders, subscriptions и други checkout разширения.
		 */
		if (! self::is_payment_complete_context() && ! Helpers::is_allowed_order_status($status)) {
			return self::result(
				false,
				'order_status_not_allowed',
				array(
					'status' => $status,
				)
			);
		}

		return self::result(
			true,
			'ok',
			array(
				'order_id'      => (int) $order->get_id(),
				'billing_email' => $billing_email,
				'order_status'  => $status,
				'is_paid'       => true,
			)
		);
	}

	/**
	 * Проверява item-level допустимост.
	 *
	 * @param \WC_Order              $order WooCommerce order.
	 * @param \WC_Order_Item_Product $item WooCommerce order item.
	 * @return array<string, mixed>
	 */
	public static function check_order_item(\WC_Order $order, \WC_Order_Item_Product $item): array
	{
		$order_result = self::check_order($order);

		if (! (bool) Helpers::array_get($order_result, 'eligible', false)) {
			return $order_result;
		}

		$order_id      = (int) $order->get_id();
		$order_item_id = (int) $item->get_id();
		$product_id    = (int) $item->get_product_id();
		$variation_id  = (int) $item->get_variation_id();

		if ($order_item_id <= 0) {
			return self::result(false, 'invalid_order_item_id');
		}

		if ($product_id <= 0) {
			return self::result(
				false,
				'invalid_product_id',
				array(
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
				)
			);
		}

		$product = $item->get_product();

		if (! $product instanceof \WC_Product) {
			return self::result(
				false,
				'product_object_missing',
				array(
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'product_id'    => $product_id,
					'variation_id'  => $variation_id,
				)
			);
		}

		$dispatch_key = Helpers::build_dispatch_key($order_id, $order_item_id, $product_id);

		if (DispatchRepository::was_sent_successfully($dispatch_key)) {
			return self::result(
				false,
				'already_sent',
				array(
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'product_id'    => $product_id,
					'variation_id'  => $variation_id,
					'dispatch_key'  => $dispatch_key,
				)
			);
		}

		$template = TemplateResolver::resolve_for_order_item($item);

		if (! (bool) Helpers::array_get($template, 'is_valid', false)) {
			return self::result(
				false,
				'template_unavailable',
				array(
					'order_id'            => $order_id,
					'order_item_id'       => $order_item_id,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'dispatch_key'        => $dispatch_key,
					'template_error'      => (string) Helpers::array_get($template, 'error', ''),
					'template_source'     => (string) Helpers::array_get($template, 'source', ''),
					'template_identifier' => (string) Helpers::array_get($template, 'template_identifier', ''),
				)
			);
		}

		return self::result(
			true,
			'ok',
			array(
				'order_id'            => $order_id,
				'order_item_id'       => $order_item_id,
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
				'dispatch_key'        => $dispatch_key,
				'billing_email'       => Helpers::sanitize_email_address((string) $order->get_billing_email()),
				'template_source'     => (string) Helpers::array_get($template, 'source', ''),
				'template_identifier' => (string) Helpers::array_get($template, 'template_identifier', ''),
				'use_fallback'        => (bool) Helpers::array_get($template, 'use_fallback', false),
				'template'            => $template,
			)
		);
	}

	/**
	 * Проверява дали поръчката е paid според WooCommerce.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public static function is_paid_order(\WC_Order $order): bool
	{
		if (method_exists($order, 'is_paid') && $order->is_paid()) {
			return true;
		}

		if (method_exists($order, 'get_date_paid') && $order->get_date_paid() instanceof \WC_DateTime) {
			return true;
		}

		$status = self::normalize_order_status((string) $order->get_status());

		return in_array($status, array('processing', 'completed'), true);
	}

	/**
	 * Нормализира status без wc- префикс.
	 *
	 * @param string $status Статус.
	 * @return string
	 */
	public static function normalize_order_status(string $status): string
	{
		$status = trim(strtolower($status));
		$status = preg_replace('/^wc-/', '', $status);

		return is_string($status) ? $status : '';
	}

	/**
	 * Проверява дали текущият dispatch е в контекста на payment_complete hook.
	 *
	 * Това позволява да не режем платени поръчки заради custom status,
	 * когато WooCommerce вече е потвърдил завършено плащане.
	 *
	 * @return bool
	 */
	private static function is_payment_complete_context(): bool
	{
		return current_filter() === 'woocommerce_payment_complete' || doing_action('woocommerce_payment_complete');
	}

	/**
	 * Генерира стандартен result payload.
	 *
	 * @param bool                 $eligible Резултат.
	 * @param string               $reason Причина.
	 * @param array<string, mixed> $context Контекст.
	 * @return array<string, mixed>
	 */
	private static function result(bool $eligible, string $reason, array $context = array()): array
	{
		return array(
			'eligible' => $eligible,
			'reason'   => sanitize_key($reason),
			'context'  => $context,
		);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}