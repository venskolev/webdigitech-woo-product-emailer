<?php
/**
 * Capability помощник за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Централизира проверки за достъп.
 */
final class Capabilities
{
	/**
	 * Основната capability за управление на плъгина.
	 */
	public static function manage_capability(): string
	{
		return Constants::CAPABILITY_MANAGE;
	}

	/**
	 * Проверява дали текущият потребител може да управлява плъгина.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool
	{
		return current_user_can(self::manage_capability());
	}

	/**
	 * Спира изпълнението, ако потребителят няма права.
	 *
	 * @return void
	 */
	public static function enforce_manage_capability(): void
	{
		if (self::current_user_can_manage()) {
			return;
		}

		wp_die(
			esc_html__(
				'Sorry, you are not allowed to access this page.',
				'webdigitech-woo-product-emailer'
			),
			esc_html__('Access denied', 'webdigitech-woo-product-emailer'),
			array(
				'response' => 403,
			)
		);
	}

	/**
	 * Проверява capability за AJAX заявки.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_ajax(): bool
	{
		return self::current_user_can_manage();
	}

	/**
	 * Проверява дали потребителят може да вижда логовете.
	 *
	 * @return bool
	 */
	public static function current_user_can_view_logs(): bool
	{
		return self::current_user_can_manage();
	}

	/**
	 * Проверява дали потребителят може да ползва tools страницата.
	 *
	 * @return bool
	 */
	public static function current_user_can_use_tools(): bool
	{
		return self::current_user_can_manage();
	}

	/**
	 * Проверява дали потребителят може да редактира product email полетата.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function current_user_can_edit_product_email_fields(int $product_id = 0): bool
	{
		if ($product_id > 0) {
			return current_user_can('edit_post', $product_id) && self::current_user_can_manage();
		}

		return self::current_user_can_manage();
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}