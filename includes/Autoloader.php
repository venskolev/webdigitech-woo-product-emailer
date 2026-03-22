<?php
/**
 * PSR-4 подобен autoloader за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за автоматичното зареждане на класовете в плъгина.
 */
final class Autoloader
{
	/**
	 * Базов namespace на плъгина.
	 */
	private const BASE_NAMESPACE = __NAMESPACE__ . '\\';

	/**
	 * Базова директория за класовете.
	 */
	private const BASE_DIRECTORY = WDT_WCPE_DIR . 'includes/';

	/**
	 * Регистрира autoloader-а.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		spl_autoload_register(array(__CLASS__, 'autoload'));
	}

	/**
	 * Зарежда клас по namespace конвенцията на плъгина.
	 *
	 * @param string $class_name Пълното име на класа.
	 * @return void
	 */
	public static function autoload(string $class_name): void
	{
		if (! self::is_plugin_class($class_name)) {
			return;
		}

		$relative_class = substr($class_name, strlen(self::BASE_NAMESPACE));

		if ($relative_class === false || $relative_class === '') {
			return;
		}

		$file_path = self::map_class_to_file($relative_class);

		if ($file_path === '') {
			return;
		}

		if (! is_readable($file_path)) {
			return;
		}

		require_once $file_path;
	}

	/**
	 * Проверява дали класът принадлежи на namespace-а на плъгина.
	 *
	 * @param string $class_name Пълното име на класа.
	 * @return bool
	 */
	private static function is_plugin_class(string $class_name): bool
	{
		return strncmp($class_name, self::BASE_NAMESPACE, strlen(self::BASE_NAMESPACE)) === 0;
	}

	/**
	 * Преобразува namespace class name към физически PHP файл.
	 *
	 * Пример:
	 * Admin\SettingsPage => includes/Admin/SettingsPage.php
	 *
	 * @param string $relative_class Името на класа без базовия namespace.
	 * @return string
	 */
	private static function map_class_to_file(string $relative_class): string
	{
		$normalized_class = str_replace('\\', '/', $relative_class);
		$normalized_class = ltrim($normalized_class, '/');

		if ($normalized_class === '') {
			return '';
		}

		return self::BASE_DIRECTORY . $normalized_class . '.php';
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}

Autoloader::register();