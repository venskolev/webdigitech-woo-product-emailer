<?php
/**
 * Помощен query слой за извличане на WooCommerce поръчки.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Woo;

use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Централизиран helper за извличане на order данни от WooCommerce.
 *
 * Този клас:
 * - намира order обект по ID;
 * - валидира дали order е наличен;
 * - връща базови customer/order данни в стандартизиран вид;
 * - помага на другите слоеве да не работят директно с Woo API навсякъде.
 */
final class OrderQuery
{
	/**
	 * Връща Woo order по ID.
	 *
	 * @param int|string $order_id WooCommerce order ID.
	 * @return \WC_Order|null
	 */
	public static function get_order(int|string $order_id): ?\WC_Order
	{
		$normalized_order_id = self::normalize_order_id($order_id);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'OrderQuery::get_order received invalid order ID.',
				array(
					'raw_order_id' => $order_id,
				)
			);

			return null;
		}

		if (! function_exists('wc_get_order')) {
			self::log_error(
				'OrderQuery::get_order failed because wc_get_order is unavailable.',
				array(
					'order_id' => $normalized_order_id,
				)
			);

			return null;
		}

		$order = wc_get_order($normalized_order_id);

		if (! $order instanceof \WC_Order) {
			self::log_debug(
				'OrderQuery::get_order could not load order.',
				array(
					'order_id' => $normalized_order_id,
				)
			);

			return null;
		}

		return $order;
	}

	/**
	 * Проверява дали order съществува.
	 *
	 * @param int|string $order_id WooCommerce order ID.
	 * @return bool
	 */
	public static function order_exists(int|string $order_id): bool
	{
		return self::get_order($order_id) instanceof \WC_Order;
	}

	/**
	 * Връща ID на клиента от order-а.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return int
	 */
	public static function get_customer_id(int|string|\WC_Order $order): int
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return 0;
		}

		return max(0, (int) $order_object->get_customer_id());
	}

	/**
	 * Връща имейла на клиента от поръчката.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return string
	 */
	public static function get_customer_email(int|string|\WC_Order $order): string
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return '';
		}

		$email = (string) $order_object->get_billing_email();
		$email = sanitize_email($email);

		return is_email($email) ? $email : '';
	}

	/**
	 * Връща основни customer данни в стандартизиран вид.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return array<string, mixed>
	 */
	public static function get_customer_data(int|string|\WC_Order $order): array
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return array(
				'customer_id' => 0,
				'email'       => '',
				'first_name'  => '',
				'last_name'   => '',
				'full_name'   => '',
			);
		}

		$first_name = trim((string) $order_object->get_billing_first_name());
		$last_name  = trim((string) $order_object->get_billing_last_name());
		$full_name  = trim($first_name . ' ' . $last_name);

		return array(
			'customer_id' => max(0, (int) $order_object->get_customer_id()),
			'email'       => self::get_customer_email($order_object),
			'first_name'  => $first_name,
			'last_name'   => $last_name,
			'full_name'   => $full_name,
		);
	}

	/**
	 * Връща базови order данни в стандартизиран вид.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return array<string, mixed>
	 */
	public static function get_order_data(int|string|\WC_Order $order): array
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return array(
				'order_id'         => 0,
				'order_number'     => '',
				'status'           => '',
				'currency'         => '',
				'total'            => '',
				'payment_method'   => '',
				'payment_title'    => '',
				'created_via'      => '',
				'customer_note'    => '',
				'date_created_gmt' => '',
			);
		}

		$date_created = $order_object->get_date_created();

		return array(
			'order_id'         => (int) $order_object->get_id(),
			'order_number'     => (string) $order_object->get_order_number(),
			'status'           => self::normalize_status((string) $order_object->get_status()),
			'currency'         => (string) $order_object->get_currency(),
			'total'            => (string) $order_object->get_total(),
			'payment_method'   => (string) $order_object->get_payment_method(),
			'payment_title'    => (string) $order_object->get_payment_method_title(),
			'created_via'      => (string) $order_object->get_created_via(),
			'customer_note'    => (string) $order_object->get_customer_note(),
			'date_created_gmt' => $date_created ? (string) $date_created->date('Y-m-d H:i:s') : '',
		);
	}

	/**
	 * Връща line items от order-а.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return array<int, \WC_Order_Item_Product>
	 */
	public static function get_line_items(int|string|\WC_Order $order): array
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return array();
		}

		$items = $order_object->get_items('line_item');

		if (! is_array($items)) {
			return array();
		}

		$normalized = array();

		foreach ($items as $item) {
			if ($item instanceof \WC_Order_Item_Product) {
				$normalized[] = $item;
			}
		}

		return $normalized;
	}

	/**
	 * Проверява дали поръчката има customer email.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return bool
	 */
	public static function has_customer_email(int|string|\WC_Order $order): bool
	{
		return self::get_customer_email($order) !== '';
	}

	/**
	 * Проверява дали order изглежда годен за product-email процес.
	 *
	 * Това е лека техническа проверка, не business eligibility.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return bool
	 */
	public static function is_technically_processable(int|string|\WC_Order $order): bool
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return false;
		}

		if ((int) $order_object->get_id() <= 0) {
			return false;
		}

		if (! self::has_customer_email($order_object)) {
			return false;
		}

		return count(self::get_line_items($order_object)) > 0;
	}

	/**
	 * Връща meta стойност от поръчката.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @param string $meta_key Meta key.
	 * @param bool $single Single mode.
	 * @return mixed
	 */
	public static function get_meta(int|string|\WC_Order $order, string $meta_key, bool $single = true): mixed
	{
		$order_object = self::resolve_order($order);

		if (! $order_object instanceof \WC_Order) {
			return $single ? '' : array();
		}

		return $order_object->get_meta($meta_key, $single);
	}

	/**
	 * Връща recent order IDs за recovery scan.
	 *
	 * Използва allowed statuses и lookback прозорец, за да намери скорошни
	 * поръчки, които потенциално са били пропуснати от normal hooks.
	 *
	 * @param array<int, string>|string $allowed_statuses Позволени order statuses.
	 * @param int $lookback_hours Колко часа назад да се гледа.
	 * @param int $limit Максимален брой резултати.
	 * @return int[]
	 */
	public static function get_recent_paid_order_ids_for_recovery(array|string $allowed_statuses, int $lookback_hours, int $limit): array
	{
		if (! function_exists('wc_get_orders')) {
			self::log_error(
				'OrderQuery::get_recent_paid_order_ids_for_recovery failed because wc_get_orders is unavailable.',
				array(
					'lookback_hours' => $lookback_hours,
					'limit'          => $limit,
				)
			);

			return array();
		}

		$statuses = Helpers::normalize_order_statuses($allowed_statuses);
		$limit    = max(1, min(200, $limit));
		$hours    = max(1, $lookback_hours);

		$date_after = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));

		$args = array(
			'type'         => 'shop_order',
			'status'       => $statuses,
			'limit'        => $limit,
			'return'       => 'ids',
			'orderby'      => 'date',
			'order'        => 'DESC',
			'date_created' => '>=' . $date_after,
		);

		try {
			$order_ids = wc_get_orders($args);
		} catch (\Throwable $exception) {
			self::log_error(
				'OrderQuery::get_recent_paid_order_ids_for_recovery query failed.',
				array(
					'statuses'       => $statuses,
					'lookback_hours' => $hours,
					'limit'          => $limit,
					'date_after_gmt' => $date_after,
					'exception'      => $exception->getMessage(),
				)
			);

			return array();
		}

		if (! is_array($order_ids) || $order_ids === array()) {
			self::log_debug(
				'OrderQuery::get_recent_paid_order_ids_for_recovery returned no orders.',
				array(
					'statuses'       => $statuses,
					'lookback_hours' => $hours,
					'limit'          => $limit,
					'date_after_gmt' => $date_after,
				)
			);

			return array();
		}

		$normalized_ids = array();

		foreach ($order_ids as $order_id) {
			$order_id = self::normalize_order_id($order_id);

			if ($order_id > 0) {
				$normalized_ids[] = $order_id;
			}
		}

		$normalized_ids = array_values(array_unique($normalized_ids));

		self::log_debug(
			'OrderQuery::get_recent_paid_order_ids_for_recovery loaded orders successfully.',
			array(
				'statuses'       => $statuses,
				'lookback_hours' => $hours,
				'limit'          => $limit,
				'date_after_gmt' => $date_after,
				'count'          => count($normalized_ids),
				'order_ids'      => $normalized_ids,
			)
		);

		return $normalized_ids;
	}

	/**
	 * Връща order object от подаден mixed вход.
	 *
	 * @param int|string|\WC_Order $order Order ID или order обект.
	 * @return \WC_Order|null
	 */
	private static function resolve_order(int|string|\WC_Order $order): ?\WC_Order
	{
		if ($order instanceof \WC_Order) {
			return $order;
		}

		return self::get_order($order);
	}

	/**
	 * Нормализира order ID.
	 *
	 * @param mixed $order_id Raw order ID.
	 * @return int
	 */
	private static function normalize_order_id(mixed $order_id): int
	{
		if (! is_numeric($order_id)) {
			return 0;
		}

		$order_id = (int) $order_id;

		return $order_id > 0 ? $order_id : 0;
	}

	/**
	 * Нормализира WooCommerce статус без wc- префикс.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	private static function normalize_status(string $status): string
	{
		$status = trim(strtolower($status));
		$status = preg_replace('/^wc-/', '', $status);

		return is_string($status) ? $status : '';
	}

	/**
	 * Safe wrapper за debug лог.
	 *
	 * @param string $message Съобщение.
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
	 * Safe wrapper за error лог.
	 *
	 * @param string $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	private static function log_error(string $message, array $context = array()): void
	{
		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Logger')) {
			Logger::log_error($message, $context);
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}