<?php
/**
 * Общ footer renderer за admin страниците на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Рендерира стандартизирания footer, използван във всички admin страници на плъгина.
 */
final class FooterRenderer
{
	/**
	 * Рендерира footer блока.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		echo '<hr style="margin-top: 40px; margin-bottom: 20px;" />';

		echo '<div style="text-align: center; font-size: 13px; color: #777;">';

		echo '<p style="margin-bottom: 6px;">';
		echo wp_kses_post(
			sprintf(
				__('© %1$s All rights reserved. | Developed by %2$s', 'webdigitech-woo-product-emailer'),
				esc_html(wp_date('Y')),
				'<a href="https://webdigitech.de" target="_blank" rel="noopener noreferrer" style="color:#0073aa;text-decoration:none;">Ventsislav Kolev | WebDigiTech</a>'
			)
		);
		echo '</p>';

		echo '<p style="margin-top: 0;">';
		echo esc_html(
			sprintf(
				__('Plugin version: %s', 'webdigitech-woo-product-emailer'),
				defined('WDT_WCPE_VERSION') ? (string) WDT_WCPE_VERSION : '1.0.0'
			)
		);
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}