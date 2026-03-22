<?php
/**
 * Migration orchestration за DB слоя на плъгина.
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
 * Отговаря за инсталация, upgrade и синхронизация на DB схемата.
 */
final class Migrations
{
	/**
	 * Стартира пълна DB синхронизация.
	 *
	 * Подходящо е както при activation, така и при runtime upgrade check.
	 *
	 * @return bool
	 */
	public static function maybe_migrate(): bool
	{
		self::ensure_schema_class_loaded();

		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Schema')) {
			return false;
		}

		$installed_version = self::get_installed_version();

		if (! self::needs_migration($installed_version)) {
			return true;
		}

		return self::run_migrations($installed_version);
	}

	/**
	 * Форсира изпълнение на DB синхронизацията.
	 *
	 * @return bool
	 */
	public static function force_migrate(): bool
	{
		self::ensure_schema_class_loaded();

		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Schema')) {
			return false;
		}

		return self::run_migrations(self::get_installed_version());
	}

	/**
	 * Връща дали е нужна миграция.
	 *
	 * @param string $installed_version Инсталираната DB версия.
	 * @return bool
	 */
	public static function needs_migration(string $installed_version = ''): bool
	{
		if ($installed_version === '') {
			$installed_version = self::get_installed_version();
		}

		if (! Schema::log_table_exists()) {
			return true;
		}

		if ($installed_version === '') {
			return true;
		}

		return version_compare($installed_version, Constants::DB_VERSION, '<');
	}

	/**
	 * Връща текущо записаната DB версия.
	 *
	 * @return string
	 */
	public static function get_installed_version(): string
	{
		$version = get_option(Constants::OPTION_DB_VERSION, '');

		return is_string($version) ? $version : '';
	}

	/**
	 * Изпълнява migration стъпките.
	 *
	 * В текущата архитектура ползваме dbDelta през Schema::create_tables(),
	 * което безопасно създава/обновява таблицата. Методът е оставен
	 * разширяем за бъдещи версионни миграции.
	 *
	 * @param string $installed_version Инсталираната DB версия.
	 * @return bool
	 */
	private static function run_migrations(string $installed_version): bool
	{
		try {
			/*
			 * Начална инсталация или възстановяване на липсваща таблица.
			 */
			if ($installed_version === '' || ! Schema::log_table_exists()) {
				Schema::create_tables();
				self::update_installed_version(Constants::DB_VERSION);

				return true;
			}

			/*
			 * Версионни миграции.
			 * За версия 1.0.0 dbDelta е достатъчен, защото таблицата е една
			 * и структурата се управлява централизирано от Schema.php.
			 *
			 * Бъдещи примери:
			 * if (version_compare($installed_version, '1.1.0', '<')) {
			 *     self::migrate_to_1_1_0();
			 * }
			 */
			Schema::create_tables();
			self::update_installed_version(Constants::DB_VERSION);

			return true;
		} catch (\Throwable $exception) {
			if (function_exists('error_log')) {
				error_log(
					sprintf(
						'[WebDigiTech Woo Product Emailer] Database migration failed: %s',
						$exception->getMessage()
					)
				);
			}

			return false;
		}
	}

	/**
	 * Обновява записаната DB версия.
	 *
	 * @param string $version Версията за запис.
	 * @return void
	 */
	private static function update_installed_version(string $version): void
	{
		update_option(Constants::OPTION_DB_VERSION, $version, false);
	}

	/**
	 * Зарежда Schema класа при нужда.
	 *
	 * @return void
	 */
	private static function ensure_schema_class_loaded(): void
	{
		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Database\\Schema')) {
			return;
		}

		$schema_file = WDT_WCPE_DIR . 'includes/Database/Schema.php';

		if (is_readable($schema_file)) {
			require_once $schema_file;
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}