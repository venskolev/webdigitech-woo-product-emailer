<?php
/**
 * DB схема за лог таблицата на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Database;

use WebDigiTech\WooProductEmailer\Constants;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за създаването и обновяването на DB таблиците.
 */
final class Schema
{
	/**
	 * Създава или обновява нужните таблици.
	 *
	 * @return void
	 */
	public static function create_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = Constants::log_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dispatch_key VARCHAR(191) NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			customer_email VARCHAR(190) NOT NULL DEFAULT '',
			template_source VARCHAR(50) NOT NULL DEFAULT '',
			template_identifier VARCHAR(191) NOT NULL DEFAULT '',
			email_subject TEXT NULL,
			email_heading TEXT NULL,
			email_body_html LONGTEXT NULL,
			email_body_text LONGTEXT NULL,
			trigger_source VARCHAR(100) NOT NULL DEFAULT '',
			send_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts INT UNSIGNED NOT NULL DEFAULT 0,
			last_error LONGTEXT NULL,
			payload_hash VARCHAR(64) NOT NULL DEFAULT '',
			sent_at DATETIME NULL DEFAULT NULL,
			last_attempt_at DATETIME NULL DEFAULT NULL,
			next_retry_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY dispatch_key (dispatch_key),
			KEY order_id (order_id),
			KEY order_item_id (order_item_id),
			KEY product_id (product_id),
			KEY variation_id (variation_id),
			KEY send_status (send_status),
			KEY trigger_source (trigger_source),
			KEY customer_email (customer_email),
			KEY sent_at (sent_at),
			KEY next_retry_at (next_retry_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta($sql);
	}

	/**
	 * Връща SQL за drop на таблицата.
	 *
	 * @return string
	 */
	public static function get_drop_table_sql(): string
	{
		$table_name = Constants::log_table_name();

		return "DROP TABLE IF EXISTS {$table_name}";
	}

	/**
	 * Изтрива таблиците на плъгина.
	 *
	 * @return void
	 */
	public static function drop_tables(): void
	{
		global $wpdb;

		$wpdb->query(self::get_drop_table_sql());
	}

	/**
	 * Проверява дали лог таблицата съществува.
	 *
	 * @return bool
	 */
	public static function log_table_exists(): bool
	{
		global $wpdb;

		$table_name = Constants::log_table_name();
		$prepared = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
		$found_table = $wpdb->get_var($prepared);

		return is_string($found_table) && $found_table === $table_name;
	}

	/**
	 * Връща текущата DB версия от options.
	 *
	 * @return string
	 */
	public static function get_installed_db_version(): string
	{
		$db_version = get_option(Constants::OPTION_DB_VERSION, '');

		return is_string($db_version) ? $db_version : '';
	}

	/**
	 * Проверява дали DB схемата е актуална.
	 *
	 * @return bool
	 */
	public static function is_up_to_date(): bool
	{
		return self::log_table_exists() && self::get_installed_db_version() === Constants::DB_VERSION;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}