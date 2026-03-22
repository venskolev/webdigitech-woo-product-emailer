<?php
/**
 * Регистрация и обработка на WooCommerce hooks за product email логиката.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Woo;

use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Logger;
use WebDigiTech\WooProductEmailer\Woo\OrderListener;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Централизирана регистрация на WooCommerce hook-ове.
 *
 * Този клас:
 * - регистрира ключовите WooCommerce събития;
 * - нормализира входа;
 * - подава order ID към централния processor;
 * - не дублира eligibility логиката, защото тя вече е в OrderEmailProcessor / Eligibility.
 */
final class Hooks
{
	/**
	 * Предпазва от двойна регистрация на hook-овете.
	 */
	private static bool $registered = false;

	/**
	 * Регистрира всички WooCommerce hooks на плъгина.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		if (self::$registered) {
			return;
		}

		if (! class_exists('\\WooCommerce') || ! function_exists('wc_get_order')) {
			self::log_debug(
				'Woo hooks registration skipped because WooCommerce is not available.'
			);

			return;
		}

		add_action(
			'woocommerce_payment_complete',
			array(__CLASS__, 'handle_payment_complete'),
			10,
			1
		);

		add_action(
			'woocommerce_order_status_changed',
			array(__CLASS__, 'handle_order_status_changed'),
			10,
			4
		);

		add_action(
			'woocommerce_order_status_processing',
			array(__CLASS__, 'handle_order_status_processing'),
			10,
			2
		);

		add_action(
			'woocommerce_order_status_completed',
			array(__CLASS__, 'handle_order_status_completed'),
			10,
			2
		);

		self::$registered = true;

		self::log_debug(
			'WooCommerce hooks registered successfully.',
			array(
				'registered_hooks' => array(
					Constants::TRIGGER_PAYMENT_COMPLETE,
					Constants::TRIGGER_ORDER_STATUS_CHANGED,
					Constants::TRIGGER_ORDER_STATUS_PROCESSING,
					Constants::TRIGGER_ORDER_STATUS_COMPLETED,
				),
			)
		);
	}

	/**
	 * Обработва payment complete hook.
	 *
	 * @param int|string $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function handle_payment_complete(int|string $order_id): void
	{
		$normalized_order_id = self::normalize_order_id($order_id);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'Payment complete hook received invalid order ID.',
				array(
					'raw_order_id' => $order_id,
					'trigger'      => Constants::TRIGGER_PAYMENT_COMPLETE,
				)
			);

			return;
		}

		self::dispatch_order(
			$normalized_order_id,
			Constants::TRIGGER_PAYMENT_COMPLETE,
			array()
		);
	}

	/**
	 * Обработва general status changed hook.
	 *
	 * @param int|string      $order_id WooCommerce order ID.
	 * @param string          $old_status Стар статус.
	 * @param string          $new_status Нов статус.
	 * @param \WC_Order|false $order Woo order object, когато е наличен.
	 * @return void
	 */
	public static function handle_order_status_changed(
		int|string $order_id,
		string $old_status,
		string $new_status,
		$order = false
	): void {
		$normalized_order_id = self::normalize_order_id($order_id, $order);
		$old_status = self::normalize_status($old_status);
		$new_status = self::normalize_status($new_status);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'Order status changed hook received invalid order ID.',
				array(
					'raw_order_id' => $order_id,
					'old_status'   => $old_status,
					'new_status'   => $new_status,
					'trigger'      => Constants::TRIGGER_ORDER_STATUS_CHANGED,
				)
			);

			return;
		}

		self::dispatch_order(
			$normalized_order_id,
			Constants::TRIGGER_ORDER_STATUS_CHANGED,
			array(
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * Обработва processing status hook.
	 *
	 * @param int|string      $order_id WooCommerce order ID.
	 * @param \WC_Order|false $order Woo order object, когато е наличен.
	 * @return void
	 */
	public static function handle_order_status_processing(int|string $order_id, $order = false): void
	{
		$normalized_order_id = self::normalize_order_id($order_id, $order);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'Processing status hook received invalid order ID.',
				array(
					'raw_order_id' => $order_id,
					'trigger'      => Constants::TRIGGER_ORDER_STATUS_PROCESSING,
				)
			);

			return;
		}

		self::dispatch_order(
			$normalized_order_id,
			Constants::TRIGGER_ORDER_STATUS_PROCESSING,
			array(
				'new_status' => 'processing',
			)
		);
	}

	/**
	 * Обработва completed status hook.
	 *
	 * @param int|string      $order_id WooCommerce order ID.
	 * @param \WC_Order|false $order Woo order object, когато е наличен.
	 * @return void
	 */
	public static function handle_order_status_completed(int|string $order_id, $order = false): void
	{
		$normalized_order_id = self::normalize_order_id($order_id, $order);

		if ($normalized_order_id <= 0) {
			self::log_debug(
				'Completed status hook received invalid order ID.',
				array(
					'raw_order_id' => $order_id,
					'trigger'      => Constants::TRIGGER_ORDER_STATUS_COMPLETED,
				)
			);

			return;
		}

		self::dispatch_order(
			$normalized_order_id,
			Constants::TRIGGER_ORDER_STATUS_COMPLETED,
			array(
				'new_status' => 'completed',
			)
		);
	}

	/**
	 * Подавa order към централния processor.
	 *
	 * Тук умишлено не правим eligibility checks,
	 * защото те вече са централизирани в OrderEmailProcessor / Eligibility.
	 *
	 * @param int                  $order_id Order ID.
	 * @param string               $trigger_source Източникът на hook-а.
	 * @param array<string, mixed> $context Допълнителен контекст за debug.
	 * @return void
	 */
	private static function dispatch_order(int $order_id, string $trigger_source, array $context = array()): void
	{
		if ($order_id <= 0) {
			return;
		}

		self::log_debug(
			'Dispatching order from Woo hook.',
			array(
				'order_id'       => $order_id,
				'trigger_source' => $trigger_source,
				'context'        => $context,
			)
		);

		try {
			$result = OrderListener::handle($order_id, $trigger_source, $context);

			self::log_debug(
				'Woo hook order dispatch completed.',
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
					'result_ok'      => (bool) ($result['ok'] ?? false),
					'items_count'    => is_array($result['items'] ?? null) ? count($result['items']) : 0,
					'meta'           => is_array($result['meta'] ?? null) ? $result['meta'] : array(),
				)
			);
		} catch (\Throwable $exception) {
			self::log_error(
				'Woo hook order dispatch failed.',
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
					'context'        => $context,
					'exception'      => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Нормализира order ID от hook аргументи.
	 *
	 * @param mixed          $order_id Order ID или друг вход.
	 * @param \WC_Order|bool $order Woo order object, ако е наличен.
	 * @return int
	 */
	private static function normalize_order_id(mixed $order_id, $order = false): int
	{
		if (is_numeric($order_id)) {
			$normalized = (int) $order_id;

			if ($normalized > 0) {
				return $normalized;
			}
		}

		if ($order instanceof \WC_Order) {
			return (int) $order->get_id();
		}

		return 0;
	}

	/**
	 * Нормализира WooCommerce статус без wc- префикс.
	 *
	 * @param string $status Статус.
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
	 * Safe wrapper за error лог.
	 *
	 * @param string               $message Съобщение.
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