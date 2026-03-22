<?php
/**
 * Listener/orchestrator за обработка на WooCommerce order събития.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Woo;

use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;
use WebDigiTech\WooProductEmailer\OrderEmailProcessor;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Централизиран listener за order-based събития.
 *
 * Този клас:
 * - нормализира order ID и trigger source;
 * - проверява базови условия за изпълнение;
 * - извиква централния processor;
 * - връща стандартизиран резултат за hooks / cron / manual действия.
 */
final class OrderListener
{
	/**
	 * Обработва поръчка по подаден order ID и trigger source.
	 *
	 * @param int|string $order_id WooCommerce order ID.
	 * @param string     $trigger_source Източник на задействане.
	 * @param array<string, mixed> $context Допълнителен контекст за debug лог.
	 * @return array<string, mixed>
	 */
	public static function handle(int|string $order_id, string $trigger_source, array $context = array()): array
	{
		$normalized_order_id = self::normalize_order_id($order_id);
		$trigger_source = self::normalize_trigger_source($trigger_source);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'OrderListener received invalid order ID.',
				array(
					'raw_order_id'   => $order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
				)
			);

			return self::result(
				false,
				0,
				$trigger_source,
				array(),
				array(
					'error' => 'Invalid order ID.',
				)
			);
		}

		if (! self::can_process()) {
			self::log_debug(
				'OrderListener skipped because plugin processing is disabled.',
				array(
					'order_id'       => $normalized_order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
				)
			);

			return self::result(
				false,
				$normalized_order_id,
				$trigger_source,
				array(),
				array(
					'error' => 'Plugin processing is disabled.',
				)
			);
		}

		self::log_debug(
			'OrderListener dispatch started.',
			array(
				'order_id'       => $normalized_order_id,
				'trigger_source' => $trigger_source,
				'context'        => $context,
			)
		);

		try {
			$processor_result = OrderEmailProcessor::process_order($normalized_order_id, $trigger_source);

			$items = self::normalize_items(Helpers::array_get($processor_result, 'items', array()));
			$meta = self::normalize_meta(Helpers::array_get($processor_result, 'meta', array()));
			$ok = (bool) Helpers::array_get($processor_result, 'ok', false);

			self::log_debug(
				'OrderListener dispatch finished.',
				array(
					'order_id'       => $normalized_order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
					'ok'             => $ok,
					'items_count'    => count($items),
					'meta'           => $meta,
				)
			);

			return self::result(
				$ok,
				$normalized_order_id,
				$trigger_source,
				$items,
				$meta
			);
		} catch (\Throwable $exception) {
			self::log_error(
				'OrderListener dispatch failed with exception.',
				array(
					'order_id'       => $normalized_order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
					'exception'      => $exception->getMessage(),
				)
			);

			return self::result(
				false,
				$normalized_order_id,
				$trigger_source,
				array(),
				array(
					'error' => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Обработва order обект директно.
	 *
	 * Полезно е за места, където вече имаме зареден \WC_Order.
	 *
	 * @param \WC_Order $order Woo order object.
	 * @param string $trigger_source Източник на задействане.
	 * @param array<string, mixed> $context Допълнителен контекст.
	 * @return array<string, mixed>
	 */
	public static function handle_order(\WC_Order $order, string $trigger_source, array $context = array()): array
	{
		return self::handle((int) $order->get_id(), $trigger_source, $context);
	}

	/**
	 * Проверява дали listener-ът може да обработва поръчки.
	 *
	 * @return bool
	 */
	public static function can_process(): bool
	{
		if (! class_exists('\\WooCommerce')) {
			return false;
		}

		if (! function_exists('wc_get_order')) {
			return false;
		}

		return Helpers::is_plugin_enabled();
	}

	/**
	 * Нормализира order ID.
	 *
	 * @param mixed $order_id Order ID.
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
	 * Нормализира trigger source към позволена стойност.
	 *
	 * @param string $trigger_source Trigger source.
	 * @return string
	 */
	private static function normalize_trigger_source(string $trigger_source): string
	{
		$trigger_source = trim($trigger_source);

		if (! in_array($trigger_source, Constants::allowed_trigger_sources(), true)) {
			return Constants::TRIGGER_MANUAL_ADMIN;
		}

		return $trigger_source;
	}

	/**
	 * Нормализира items масив.
	 *
	 * @param mixed $items Резултати от processor-а.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_items(mixed $items): array
	{
		if (! is_array($items)) {
			return array();
		}

		$normalized = array();

		foreach ($items as $item) {
			if (is_array($item)) {
				$normalized[] = $item;
			}
		}

		return $normalized;
	}

	/**
	 * Нормализира meta масив.
	 *
	 * @param mixed $meta Meta данни.
	 * @return array<string, mixed>
	 */
	private static function normalize_meta(mixed $meta): array
	{
		return is_array($meta) ? $meta : array();
	}

	/**
	 * Стандартизира резултата на listener слоя.
	 *
	 * @param bool $ok OK статус.
	 * @param int $order_id Order ID.
	 * @param string $trigger_source Trigger source.
	 * @param array<int, array<string, mixed>> $items Items резултати.
	 * @param array<string, mixed> $meta Meta данни.
	 * @return array<string, mixed>
	 */
	private static function result(
		bool $ok,
		int $order_id,
		string $trigger_source,
		array $items,
		array $meta
	): array {
		return array(
			'ok'             => $ok,
			'order_id'       => $order_id,
			'trigger_source' => $trigger_source,
			'items'          => $items,
			'meta'           => $meta,
		);
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