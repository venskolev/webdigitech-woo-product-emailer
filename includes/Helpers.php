<?php
/**
 * Помощни функции за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Общи helper методи за плъгина.
 */
final class Helpers
{
	/**
	 * Връща глобалните настройки, merge-нати с default стойностите.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array
	{
		$defaults = Constants::default_settings();
		$settings = get_option(Constants::OPTION_SETTINGS, array());

		if (! is_array($settings)) {
			$settings = array();
		}

		return wp_parse_args($settings, $defaults);
	}

	/**
	 * Връща конкретна настройка.
	 *
	 * @param string $key Ключ на настройката.
	 * @param mixed  $default Стойност по подразбиране.
	 * @return mixed
	 */
	public static function get_setting(string $key, mixed $default = null): mixed
	{
		$settings = self::get_settings();

		return array_key_exists($key, $settings) ? $settings[$key] : $default;
	}

	/**
	 * Проверява yes/no флаг.
	 *
	 * @param mixed $value Стойност за проверка.
	 * @return bool
	 */
	public static function is_yes(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (int) $value === 1;
		}

		if (! is_scalar($value)) {
			return false;
		}

		$value = strtolower(trim((string) $value));

		return in_array($value, array('yes', '1', 'true', 'on'), true);
	}

	/**
	 * Проверява дали плъгинът е глобално активен.
	 *
	 * @return bool
	 */
	public static function is_plugin_enabled(): bool
	{
		return self::is_yes(self::get_setting('plugin_enabled', 'yes'));
	}

	/**
	 * Проверява дали fallback email е активен.
	 *
	 * @return bool
	 */
	public static function is_fallback_email_enabled(): bool
	{
		return self::is_yes(self::get_setting('fallback_email_enabled', 'yes'));
	}

	/**
	 * Проверява дали логването е активно.
	 *
	 * @return bool
	 */
	public static function is_logging_enabled(): bool
	{
		return self::is_yes(self::get_setting('enable_logging', 'yes'));
	}

	/**
	 * Проверява дали retry логиката е активна.
	 *
	 * @return bool
	 */
	public static function is_retry_enabled(): bool
	{
		return self::is_yes(self::get_setting('retry_failed_sends', 'yes'));
	}

	/**
	 * Проверява дали recovery логиката е активна.
	 *
	 * @return bool
	 */
	public static function is_recovery_enabled(): bool
	{
		return self::is_yes(self::get_setting('recovery_enabled', 'yes'));
	}

	/**
	 * Проверява дали debug режимът е активен.
	 *
	 * @return bool
	 */
	public static function is_debug_enabled(): bool
	{
		return self::is_yes(self::get_setting('debug_mode', 'no'));
	}

	/**
	 * Проверява дали трябва да се пазят данните при uninstall.
	 *
	 * @return bool
	 */
	public static function should_preserve_data_on_uninstall(): bool
	{
		$value = self::get_setting('preserve_data_on_uninstall', 'yes');

		return self::is_yes($value);
	}

	/**
	 * Нормализира масив от статуси.
	 *
	 * @param mixed $statuses Статуси от настройки/вход.
	 * @return string[]
	 */
	public static function normalize_order_statuses(mixed $statuses): array
	{
		if (is_string($statuses)) {
			$statuses = preg_split('/[\s,|;]+/', $statuses);
		}

		if (! is_array($statuses)) {
			return Constants::DEFAULT_ALLOWED_ORDER_STATUSES;
		}

		$normalized = array();

		foreach ($statuses as $status) {
			if (! is_scalar($status)) {
				continue;
			}

			$status = trim(strtolower((string) $status));
			$status = preg_replace('/^wc-/', '', $status);

			if (! is_string($status) || $status === '') {
				continue;
			}

			$normalized[] = $status;
		}

		$normalized = array_values(array_unique($normalized));

		return $normalized !== array() ? $normalized : Constants::DEFAULT_ALLOWED_ORDER_STATUSES;
	}

	/**
	 * Връща позволените статуси за изпращане.
	 *
	 * @return string[]
	 */
	public static function get_allowed_order_statuses(): array
	{
		$statuses = self::get_setting('allowed_order_statuses', Constants::DEFAULT_ALLOWED_ORDER_STATUSES);

		return self::normalize_order_statuses($statuses);
	}

	/**
	 * Проверява дали подаденият статус е позволен.
	 *
	 * @param string $status Order статус.
	 * @return bool
	 */
	public static function is_allowed_order_status(string $status): bool
	{
		$status = trim(strtolower($status));
		$status = preg_replace('/^wc-/', '', $status);

		if (! is_string($status) || $status === '') {
			return false;
		}

		return in_array($status, self::get_allowed_order_statuses(), true);
	}

	/**
	 * Санитизира yes/no стойност.
	 *
	 * @param mixed  $value Входна стойност.
	 * @param string $default Стойност по подразбиране.
	 * @return string
	 */
	public static function sanitize_yes_no(mixed $value, string $default = 'no'): string
	{
		if (! in_array($default, array('yes', 'no'), true)) {
			$default = 'no';
		}

		if (is_bool($value)) {
			return $value ? 'yes' : 'no';
		}

		if (is_int($value) || is_float($value)) {
			return ((int) $value === 1) ? 'yes' : 'no';
		}

		if (! is_scalar($value)) {
			return $default;
		}

		$value = strtolower(trim((string) $value));

		if (in_array($value, array('yes', 'true', '1', 'on'), true)) {
			return 'yes';
		}

		if (in_array($value, array('no', 'false', '0', 'off'), true)) {
			return 'no';
		}

		return $default;
	}

	/**
	 * Санитизира текстово поле.
	 *
	 * @param mixed $value Входна стойност.
	 * @return string
	 */
	public static function sanitize_text(mixed $value): string
	{
		return is_scalar($value) ? sanitize_text_field((string) $value) : '';
	}

	/**
	 * Санитизира multiline текст.
	 *
	 * @param mixed $value Входна стойност.
	 * @return string
	 */
	public static function sanitize_textarea(mixed $value): string
	{
		return is_scalar($value) ? sanitize_textarea_field((string) $value) : '';
	}

	/**
	 * Санитизира rich HTML template съдържание.
	 *
	 * @param mixed $value Входна стойност.
	 * @return string
	 */
	public static function sanitize_template_html(mixed $value): string
	{
		if (! is_scalar($value)) {
			return '';
		}

		$html = (string) $value;

		return wp_kses($html, self::allowed_template_html_tags());
	}

	/**
	 * Връща позволени HTML тагове за email template, разширени със style атрибути.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_template_html_tags(): array
	{
		$allowed = Constants::allowed_template_html_tags();

		$tags_with_style = array(
			'a',
			'p',
			'div',
			'span',
			'ul',
			'ol',
			'li',
			'blockquote',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'table',
			'td',
			'th',
			'tr',
			'thead',
			'tbody',
			'tfoot',
			'img',
		);

		foreach ($tags_with_style as $tag) {
			if (! isset($allowed[ $tag ])) {
				$allowed[ $tag ] = array();
			}

			$allowed[ $tag ]['style'] = true;
		}

		return $allowed;
	}

	/**
	 * Санитизира plain text template съдържание.
	 *
	 * @param mixed $value Входна стойност.
	 * @return string
	 */
	public static function sanitize_template_text(mixed $value): string
	{
		if (! is_scalar($value)) {
			return '';
		}

		$value = (string) $value;
		$value = str_replace(array("\r\n", "\r"), "\n", $value);

		return trim($value);
	}

	/**
	 * Санитизира subject, защитено срещу header injection.
	 *
	 * @param mixed $value Subject стойност.
	 * @return string
	 */
	public static function sanitize_email_subject(mixed $value): string
	{
		if (! is_scalar($value)) {
			return '';
		}

		$subject = sanitize_text_field((string) $value);
		$subject = str_replace(array("\r", "\n"), '', $subject);

		return trim($subject);
	}

	/**
	 * Валидира и нормализира email адрес.
	 *
	 * @param mixed $email Имейл адрес.
	 * @return string
	 */
	public static function sanitize_email_address(mixed $email): string
	{
		if (! is_scalar($email)) {
			return '';
		}

		$email = sanitize_email((string) $email);

		return is_email($email) ? $email : '';
	}

	/**
	 * Превръща HTML в plain text fallback.
	 *
	 * @param string $html HTML съдържание.
	 * @return string
	 */
	public static function html_to_text(string $html): string
	{
		$html = wp_strip_all_tags($html, true);
		$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$html = preg_replace("/[ \t]+/", ' ', $html);
		$html = preg_replace("/\n{3,}/", "\n\n", (string) $html);

		return trim((string) $html);
	}

	/**
	 * Генерира dispatch key за order item.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $order_item_id Order item ID.
	 * @param int    $product_id Product ID.
	 * @param string $email_type Вид на имейла.
	 * @return string
	 */
	public static function build_dispatch_key(
		int $order_id,
		int $order_item_id,
		int $product_id,
		string $email_type = 'customer_product'
	): string {
		return sprintf(
			'order:%d|item:%d|product:%d|email_type:%s',
			$order_id,
			$order_item_id,
			$product_id,
			sanitize_key($email_type)
		);
	}

	/**
	 * Генерира hash на payload за диагностични цели.
	 *
	 * @param array<string, mixed> $payload Payload масив.
	 * @return string
	 */
	public static function build_payload_hash(array $payload): string
	{
		$json = wp_json_encode($payload);

		if (! is_string($json)) {
			$json = serialize($payload);
		}

		return hash('sha256', $json);
	}

	/**
	 * Връща MySQL datetime в UTC за текущия момент.
	 *
	 * @return string
	 */
	public static function now_mysql(): string
	{
		return gmdate('Y-m-d H:i:s');
	}

	/**
	 * Връща MySQL datetime след X минути.
	 *
	 * @param int $minutes Минути.
	 * @return string
	 */
	public static function mysql_datetime_after_minutes(int $minutes): string
	{
		$minutes = max(0, $minutes);

		return gmdate('Y-m-d H:i:s', time() + ($minutes * MINUTE_IN_SECONDS));
	}

	/**
	 * Нормализира integer стойност с min/max граници.
	 *
	 * @param mixed    $value Входна стойност.
	 * @param int      $default Стойност по подразбиране.
	 * @param int|null $min Минимум.
	 * @param int|null $max Максимум.
	 * @return int
	 */
	public static function normalize_int(mixed $value, int $default, ?int $min = null, ?int $max = null): int
	{
		if (is_numeric($value)) {
			$value = (int) $value;
		} else {
			$value = $default;
		}

		if ($min !== null && $value < $min) {
			$value = $min;
		}

		if ($max !== null && $value > $max) {
			$value = $max;
		}

		return $value;
	}

	/**
	 * Връща URL към admin страница на плъгина.
	 *
	 * @param string                $page_slug Slug на страницата.
	 * @param array<string, scalar> $query Допълнителни query аргументи.
	 * @return string
	 */
	public static function admin_page_url(string $page_slug, array $query = array()): string
	{
		$args = array_merge(
			array(
				'page' => $page_slug,
			),
			$query
		);

		return add_query_arg($args, admin_url('admin.php'));
	}

	/**
	 * Връща валиден mail content type.
	 *
	 * @return string
	 */
	public static function get_mail_content_type(): string
	{
		$content_type = self::get_setting('mail_content_type_mode', Constants::DEFAULT_MAIL_CONTENT_TYPE);

		if (! is_string($content_type)) {
			return Constants::DEFAULT_MAIL_CONTENT_TYPE;
		}

		return in_array($content_type, Constants::ALLOWED_MAIL_CONTENT_TYPES, true)
			? $content_type
			: Constants::DEFAULT_MAIL_CONTENT_TYPE;
	}

	/**
	 * Връща sender name override.
	 *
	 * @return string
	 */
	public static function get_sender_name_override(): string
	{
		return self::sanitize_text(self::get_setting('sender_name_override', ''));
	}

	/**
	 * Връща sender email override.
	 *
	 * @return string
	 */
	public static function get_sender_email_override(): string
	{
		return self::sanitize_email_address(self::get_setting('sender_email_override', ''));
	}

	/**
	 * Връща максималния брой retry опити.
	 *
	 * @return int
	 */
	public static function get_max_retry_attempts(): int
	{
		return self::normalize_int(
			self::get_setting('max_retry_attempts', Constants::DEFAULT_MAX_RETRY_ATTEMPTS),
			Constants::DEFAULT_MAX_RETRY_ATTEMPTS,
			1,
			20
		);
	}

	/**
	 * Връща retry интервала в минути.
	 *
	 * @return int
	 */
	public static function get_retry_interval_minutes(): int
	{
		return self::normalize_int(
			self::get_setting('retry_interval_minutes', Constants::DEFAULT_RETRY_INTERVAL_MINUTES),
			Constants::DEFAULT_RETRY_INTERVAL_MINUTES,
			1,
			1440
		);
	}

	/**
	 * Връща колко дни да се пазят логовете.
	 *
	 * @return int
	 */
	public static function get_log_retention_days(): int
	{
		return self::normalize_int(
			self::get_setting('retain_logs_days', Constants::DEFAULT_LOG_RETENTION_DAYS),
			Constants::DEFAULT_LOG_RETENTION_DAYS,
			1,
			3650
		);
	}

	/**
	 * Връща recovery lookback hours.
	 *
	 * @return int
	 */
	public static function get_recovery_lookback_hours(): int
	{
		return self::normalize_int(
			self::get_setting('recovery_lookback_hours', 24),
			24,
			1,
			720
		);
	}

	/**
	 * Връща recovery batch limit.
	 *
	 * @return int
	 */
	public static function get_recovery_batch_limit(): int
	{
		return self::normalize_int(
			self::get_setting('recovery_batch_limit', Constants::RECOVERY_BATCH_LIMIT),
			Constants::RECOVERY_BATCH_LIMIT,
			1,
			500
		);
	}

	/**
	 * Връща retry batch limit.
	 *
	 * @return int
	 */
	public static function get_retry_batch_limit(): int
	{
		return self::normalize_int(
			self::get_setting('retry_batch_limit', Constants::RETRY_BATCH_LIMIT),
			Constants::RETRY_BATCH_LIMIT,
			1,
			500
		);
	}

	/**
	 * Връща fallback email template настройките.
	 *
	 * @return array<string, string>
	 */
	public static function get_fallback_template_settings(): array
	{
		$subject   = self::sanitize_email_subject(self::get_setting('fallback_email_subject', ''));
		$heading   = self::sanitize_text(self::get_setting('fallback_email_heading', ''));
		$body_html = self::sanitize_template_html(self::get_setting('fallback_email_body_html', ''));
		$body_text = self::sanitize_template_text(self::get_setting('fallback_email_body_text', ''));

		if ($body_text === '' && $body_html !== '') {
			$body_text = self::html_to_text($body_html);
		}

		return array(
			'subject'   => $subject,
			'heading'   => $heading,
			'body_html' => $body_html,
			'body_text' => $body_text,
		);
	}

	/**
	 * Проверява дали fallback template е използваем.
	 *
	 * @return bool
	 */
	public static function has_valid_fallback_template(): bool
	{
		if (! self::is_fallback_email_enabled()) {
			return false;
		}

		$template = self::get_fallback_template_settings();

		return $template['subject'] !== '' && ($template['body_html'] !== '' || $template['body_text'] !== '');
	}

	/**
	 * Безопасно взима стойност от масив.
	 *
	 * @param array<string, mixed> $data Масив.
	 * @param string               $key Ключ.
	 * @param mixed                $default Default стойност.
	 * @return mixed
	 */
	public static function array_get(array $data, string $key, mixed $default = null): mixed
	{
		return array_key_exists($key, $data) ? $data[ $key ] : $default;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}