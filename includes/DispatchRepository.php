<?php
/**
 * Repository слой за логовете на изпратените продуктови имейли.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря за CRUD/lookup операции към dispatch log таблицата.
 */
final class DispatchRepository
{
	/**
	 * Връща името на лог таблицата.
	 *
	 * @return string
	 */
	private static function table_name(): string
	{
		return Constants::log_table_name();
	}

	/**
	 * Проверява дали вече има успешен dispatch за даден dispatch key.
	 *
	 * @param string $dispatch_key Уникален dispatch ключ.
	 * @return bool
	 */
	public static function was_sent_successfully(string $dispatch_key): bool
	{
		return self::has_dispatch_with_status($dispatch_key, Constants::STATUS_SUCCESS);
	}

	/**
	 * Алиас за обратно съвместимост.
	 *
	 * @param string $dispatch_key Уникален dispatch ключ.
	 * @return bool
	 */
	public static function has_successful_dispatch(string $dispatch_key): bool
	{
		return self::was_sent_successfully($dispatch_key);
	}

	/**
	 * Проверява дали вече има запис за даден dispatch key и статус.
	 *
	 * @param string $dispatch_key Dispatch ключ.
	 * @param string $status Статус.
	 * @return bool
	 */
	public static function has_dispatch_with_status(string $dispatch_key, string $status): bool
	{
		global $wpdb;

		$dispatch_key = sanitize_text_field($dispatch_key);
		$status       = self::sanitize_send_status($status);

		if ($dispatch_key === '' || $status === '') {
			return false;
		}

		$table_name = self::table_name();
		$sql        = $wpdb->prepare(
			"SELECT id
			FROM {$table_name}
			WHERE dispatch_key = %s
			  AND send_status = %s
			LIMIT 1",
			$dispatch_key,
			$status
		);
		$result     = $wpdb->get_var($sql);

		return ! empty($result);
	}

	/**
	 * Връща ID на лог запис по dispatch key.
	 *
	 * @param string $dispatch_key Dispatch ключ.
	 * @return int
	 */
	public static function find_log_id_by_dispatch_key(string $dispatch_key): int
	{
		global $wpdb;

		$dispatch_key = sanitize_text_field($dispatch_key);

		if ($dispatch_key === '') {
			return 0;
		}

		$table_name = self::table_name();
		$sql        = $wpdb->prepare(
			"SELECT id
			FROM {$table_name}
			WHERE dispatch_key = %s
			LIMIT 1",
			$dispatch_key
		);
		$result     = $wpdb->get_var($sql);

		return is_numeric($result) ? (int) $result : 0;
	}

	/**
	 * Връща ред по dispatch key.
	 *
	 * @param string $dispatch_key Dispatch ключ.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_dispatch_key(string $dispatch_key): ?array
	{
		global $wpdb;

		$dispatch_key = sanitize_text_field($dispatch_key);

		if ($dispatch_key === '') {
			return null;
		}

		$table_name = self::table_name();
		$sql        = $wpdb->prepare(
			"SELECT *
			FROM {$table_name}
			WHERE dispatch_key = %s
			LIMIT 1",
			$dispatch_key
		);
		$row        = $wpdb->get_row($sql, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Връща ред по ID.
	 *
	 * @param int $id ID на записа.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_id(int $id): ?array
	{
		global $wpdb;

		$id = absint($id);

		if ($id <= 0) {
			return null;
		}

		$table_name = self::table_name();
		$sql        = $wpdb->prepare(
			"SELECT *
			FROM {$table_name}
			WHERE id = %d
			LIMIT 1",
			$id
		);
		$row        = $wpdb->get_row($sql, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Създава нов dispatch лог запис.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int ID на новия запис или 0 при неуспех.
	 */
	public static function insert(array $data): int
	{
		global $wpdb;

		$payload = self::normalize_row_data_for_insert($data);

		if ($payload['dispatch_key'] === '') {
			return 0;
		}

		$result = $wpdb->insert(
			self::table_name(),
			$payload,
			self::formats_for_row($payload)
		);

		if ($result === false) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Създава или обновява dispatch запис по dispatch key.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	public static function ensure_dispatch_record(array $data): int
	{
		$dispatch_key = isset($data['dispatch_key']) ? sanitize_text_field((string) $data['dispatch_key']) : '';

		if ($dispatch_key === '') {
			return 0;
		}

		$existing_id = self::find_log_id_by_dispatch_key($dispatch_key);

		if ($existing_id > 0) {
			$updated = self::update($existing_id, $data);

			return $updated ? $existing_id : 0;
		}

		return self::insert($data);
	}

	/**
	 * Обновява запис по ID.
	 *
	 * @param int                 $id ID на записа.
	 * @param array<string,mixed> $data Данни за update.
	 * @return bool
	 */
	public static function update(int $id, array $data): bool
	{
		global $wpdb;

		$id = absint($id);

		if ($id <= 0 || $data === array()) {
			return false;
		}

		$payload = self::normalize_row_data_for_update($data);

		if ($payload === array()) {
			return false;
		}

		$result = $wpdb->update(
			self::table_name(),
			$payload,
			array('id' => $id),
			self::formats_for_row($payload),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Маркира записа като успешно изпратен.
	 *
	 * @param int                 $id ID на записа.
	 * @param string              $dispatch_key Dispatch ключ.
	 * @param array<string,mixed> $data Допълнителни данни.
	 * @return bool
	 */
	public static function mark_sent(int $id, string $dispatch_key, array $data = array()): bool
	{
		unset($dispatch_key);

		$payload = array_merge(
			$data,
			array(
				'send_status'     => Constants::STATUS_SUCCESS,
				'last_error'      => '',
				'sent_at'         => Helpers::now_mysql(),
				'last_attempt_at' => Helpers::now_mysql(),
				'next_retry_at'   => null,
			)
		);

		return self::update($id, $payload);
	}

	/**
	 * Маркира записа като успешно изпратен.
	 *
	 * @param int $id ID на записа.
	 * @return bool
	 */
	public static function mark_as_success(int $id): bool
	{
		return self::mark_sent($id, '', array());
	}

	/**
	 * Маркира запис като failed и планира следващ retry при нужда.
	 *
	 * @param int    $id ID на записа.
	 * @param string $dispatch_key Dispatch ключ.
	 * @param string $error_message Съобщение за грешка.
	 * @param int    $retry_interval_minutes Интервал в минути.
	 * @return bool
	 */
	public static function mark_failed(
		int $id,
		string $dispatch_key,
		string $error_message,
		int $retry_interval_minutes
	): bool {
		unset($dispatch_key);

		$current = self::find_by_id($id);

		if (! is_array($current)) {
			return false;
		}

		$current_attempts = isset($current['attempts']) ? absint($current['attempts']) : 0;
		$new_attempts     = $current_attempts + 1;
		$max_attempts     = Helpers::get_max_retry_attempts();
		$next_retry_at    = null;

		if ($new_attempts < $max_attempts) {
			$retry_interval_minutes = max(1, $retry_interval_minutes);
			$next_retry_at          = Helpers::mysql_datetime_after_minutes($retry_interval_minutes);
		}

		return self::update(
			$id,
			array(
				'send_status'     => Constants::STATUS_FAILED,
				'last_error'      => $error_message,
				'attempts'        => $new_attempts,
				'last_attempt_at' => Helpers::now_mysql(),
				'next_retry_at'   => $next_retry_at,
			)
		);
	}

	/**
	 * Алиас за обратно съвместимост със старото име.
	 *
	 * @param int    $id Запис ID.
	 * @param string $error_message Съобщение за грешка.
	 * @param int    $retry_count Нов retry count.
	 * @param int    $max_retry_attempts Максимален брой опити.
	 * @param int    $retry_interval_minutes Интервал в минути.
	 * @return bool
	 */
	public static function mark_as_failed(
		int $id,
		string $error_message,
		int $retry_count,
		int $max_retry_attempts,
		int $retry_interval_minutes
	): bool {
		$next_retry_at = null;

		if ($retry_count < $max_retry_attempts) {
			$retry_interval_minutes = max(1, $retry_interval_minutes);
			$next_retry_at          = Helpers::mysql_datetime_after_minutes($retry_interval_minutes);
		}

		return self::update(
			$id,
			array(
				'send_status'     => Constants::STATUS_FAILED,
				'last_error'      => $error_message,
				'attempts'        => max(0, $retry_count),
				'last_attempt_at' => Helpers::now_mysql(),
				'next_retry_at'   => $next_retry_at,
			)
		);
	}

	/**
	 * Маркира запис като skipped.
	 *
	 * @param int    $id ID на записа.
	 * @param string $dispatch_key Dispatch ключ.
	 * @param string $reason Причина.
	 * @return bool
	 */
	public static function mark_skipped(int $id, string $dispatch_key, string $reason = ''): bool
	{
		unset($dispatch_key);

		return self::update(
			$id,
			array(
				'send_status'     => Constants::STATUS_SKIPPED,
				'last_error'      => $reason,
				'last_attempt_at' => Helpers::now_mysql(),
				'next_retry_at'   => null,
			)
		);
	}

	/**
	 * Алиас за обратно съвместимост.
	 *
	 * @param int    $id ID на записа.
	 * @param string $reason Причина.
	 * @return bool
	 */
	public static function mark_as_skipped(int $id, string $reason = ''): bool
	{
		return self::mark_skipped($id, '', $reason);
	}

	/**
	 * Връща failed записи, готови за retry според глобалните настройки.
	 *
	 * @param int $max_attempts Максимален брой опити.
	 * @param int $limit Лимит.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_retry_candidates(int $max_attempts, int $limit = Constants::RETRY_BATCH_LIMIT): array
	{
		global $wpdb;

		$max_attempts = max(1, $max_attempts);
		$limit        = max(1, $limit);
		$table_name   = self::table_name();
		$now_mysql    = Helpers::now_mysql();

		$sql = $wpdb->prepare(
			"SELECT *
			FROM {$table_name}
			WHERE send_status = %s
			  AND next_retry_at IS NOT NULL
			  AND next_retry_at <= %s
			  AND attempts < %d
			ORDER BY next_retry_at ASC, id ASC
			LIMIT %d",
			Constants::STATUS_FAILED,
			$now_mysql,
			$max_attempts,
			$limit
		);

		$rows = $wpdb->get_results($sql, ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Алиас за обратно съвместимост.
	 *
	 * @param int $limit Лимит.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_retryable_failed_dispatches(int $limit = Constants::RETRY_BATCH_LIMIT): array
	{
		return self::get_retry_candidates(Helpers::get_max_retry_attempts(), $limit);
	}

	/**
	 * Връща последните логове за admin списък.
	 *
	 * @param int   $limit Лимит.
	 * @param int   $offset Offset.
	 * @param array<string,mixed> $filters Филтри.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_logs(int $limit = 50, int $offset = 0, array $filters = array()): array
	{
		global $wpdb;

		$limit      = max(1, $limit);
		$offset     = max(0, $offset);
		$table_name = self::table_name();

		$where_parts = array('1=1');
		$values      = array();

		if (! empty($filters['status']) && is_string($filters['status'])) {
			$status = self::sanitize_send_status($filters['status']);

			if ($status !== '') {
				$where_parts[] = 'send_status = %s';
				$values[]      = $status;
			}
		}

		if (! empty($filters['customer_email']) && is_string($filters['customer_email'])) {
			$where_parts[] = 'customer_email = %s';
			$values[]      = sanitize_email($filters['customer_email']);
		}

		if (! empty($filters['order_id'])) {
			$where_parts[] = 'order_id = %d';
			$values[]      = absint($filters['order_id']);
		}

		if (! empty($filters['product_id'])) {
			$where_parts[] = 'product_id = %d';
			$values[]      = absint($filters['product_id']);
		}

		$where_sql = implode(' AND ', $where_parts);
		$sql       = "SELECT *
			FROM {$table_name}
			WHERE {$where_sql}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		$prepared = $wpdb->prepare($sql, ...$values);
		$rows     = $wpdb->get_results($prepared, ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Брои логовете според подадените филтри.
	 *
	 * @param array<string,mixed> $filters Филтри.
	 * @return int
	 */
	public static function count_logs(array $filters = array()): int
	{
		global $wpdb;

		$table_name  = self::table_name();
		$where_parts = array('1=1');
		$values      = array();

		if (! empty($filters['status']) && is_string($filters['status'])) {
			$status = self::sanitize_send_status($filters['status']);

			if ($status !== '') {
				$where_parts[] = 'send_status = %s';
				$values[]      = $status;
			}
		}

		if (! empty($filters['customer_email']) && is_string($filters['customer_email'])) {
			$where_parts[] = 'customer_email = %s';
			$values[]      = sanitize_email($filters['customer_email']);
		}

		if (! empty($filters['order_id'])) {
			$where_parts[] = 'order_id = %d';
			$values[]      = absint($filters['order_id']);
		}

		if (! empty($filters['product_id'])) {
			$where_parts[] = 'product_id = %d';
			$values[]      = absint($filters['product_id']);
		}

		$where_sql = implode(' AND ', $where_parts);
		$sql       = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";

		if ($values !== array()) {
			$sql = $wpdb->prepare($sql, ...$values);
		}

		$count = $wpdb->get_var($sql);

		return is_numeric($count) ? (int) $count : 0;
	}

	/**
	 * Изтрива стари логове според retention политика.
	 *
	 * @param int $retain_days Колко дни да се пазят логовете.
	 * @return int Брой изтрити записи.
	 */
	public static function delete_logs_older_than_days(int $retain_days): int
	{
		global $wpdb;

		$retain_days = max(1, $retain_days);
		$table_name  = self::table_name();
		$cutoff      = gmdate('Y-m-d H:i:s', time() - ($retain_days * DAY_IN_SECONDS));

		$sql = $wpdb->prepare(
			"DELETE FROM {$table_name}
			WHERE created_at < %s",
			$cutoff
		);

		$result = $wpdb->query($sql);

		if ($result === false) {
			return 0;
		}

		return (int) $result;
	}

	/**
	 * Изтрива всички логове.
	 *
	 * @return int
	 */
	public static function truncate_logs(): int
	{
		global $wpdb;

		$table_name = self::table_name();
		$result     = $wpdb->query("TRUNCATE TABLE {$table_name}");

		if ($result === false) {
			return 0;
		}

		return (int) $result;
	}

	/**
	 * Нормализира и подготвя payload за insert.
	 *
	 * @param array<string,mixed> $data Сурови данни.
	 * @return array<string,mixed>
	 */
	private static function normalize_row_data_for_insert(array $data): array
	{
		$defaults = array(
			'dispatch_key'        => '',
			'order_id'            => 0,
			'order_item_id'       => 0,
			'product_id'          => 0,
			'variation_id'        => 0,
			'customer_email'      => '',
			'template_source'     => '',
			'template_identifier' => '',
			'email_subject'       => '',
			'email_heading'       => '',
			'email_body_html'     => '',
			'email_body_text'     => '',
			'trigger_source'      => '',
			'send_status'         => Constants::STATUS_PENDING,
			'attempts'            => 0,
			'last_error'          => '',
			'payload_hash'        => '',
			'sent_at'             => null,
			'last_attempt_at'     => null,
			'next_retry_at'       => null,
			'created_at'          => Helpers::now_mysql(),
			'updated_at'          => Helpers::now_mysql(),
		);

		$payload = wp_parse_args(self::sanitize_row_data($data), $defaults);

		return self::filter_allowed_columns($payload, true);
	}

	/**
	 * Нормализира и подготвя payload за update.
	 *
	 * @param array<string,mixed> $data Сурови данни.
	 * @return array<string,mixed>
	 */
	private static function normalize_row_data_for_update(array $data): array
	{
		$payload = self::sanitize_row_data($data);
		$payload = self::filter_allowed_columns($payload, false);

		if ($payload === array()) {
			return array();
		}

		$payload['updated_at'] = Helpers::now_mysql();

		return $payload;
	}

	/**
	 * Санитизира payload за insert/update.
	 *
	 * @param array<string,mixed> $data Сурови данни.
	 * @return array<string,mixed>
	 */
	private static function sanitize_row_data(array $data): array
	{
		$sanitized = array();

		foreach ($data as $key => $value) {
			switch ($key) {
				case 'id':
				case 'order_id':
				case 'order_item_id':
				case 'product_id':
				case 'variation_id':
				case 'attempts':
					$sanitized[$key] = absint($value);
					break;

				case 'customer_email':
					$sanitized[$key] = Helpers::sanitize_email_address((string) $value);
					break;

				case 'dispatch_key':
				case 'template_identifier':
				case 'payload_hash':
					$sanitized[$key] = sanitize_text_field((string) $value);
					break;

				case 'trigger_source':
					$sanitized[$key] = self::sanitize_trigger_source((string) $value);
					break;

				case 'template_source':
					$sanitized[$key] = self::sanitize_template_source((string) $value);
					break;

				case 'send_status':
				case 'status':
					$sanitized['send_status'] = self::sanitize_send_status((string) $value);
					break;

				case 'email_subject':
				case 'email_heading':
					$sanitized[$key] = Helpers::sanitize_email_subject((string) $value);
					break;

				case 'last_error':
				case 'error_message':
					$sanitized['last_error'] = Helpers::sanitize_textarea((string) $value);
					break;

				case 'email_body_html':
					$sanitized[$key] = Helpers::sanitize_template_html((string) $value);
					break;

				case 'email_body_text':
					$sanitized[$key] = Helpers::sanitize_template_text((string) $value);
					break;

				case 'next_retry_at':
				case 'last_attempt_at':
				case 'sent_at':
				case 'created_at':
				case 'updated_at':
					$sanitized[$key] = self::normalize_datetime_value($value);
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Оставя само колоните, които реално съществуват в схемата.
	 *
	 * @param array<string,mixed> $data Данни.
	 * @param bool                $include_created_fields Дали да включи created_at/updated_at.
	 * @return array<string,mixed>
	 */
	private static function filter_allowed_columns(array $data, bool $include_created_fields): array
	{
		$allowed_columns = array(
			'dispatch_key',
			'order_id',
			'order_item_id',
			'product_id',
			'variation_id',
			'customer_email',
			'template_source',
			'template_identifier',
			'email_subject',
			'email_heading',
			'email_body_html',
			'email_body_text',
			'trigger_source',
			'send_status',
			'attempts',
			'last_error',
			'payload_hash',
			'sent_at',
			'last_attempt_at',
			'next_retry_at',
		);

		if ($include_created_fields) {
			$allowed_columns[] = 'created_at';
			$allowed_columns[] = 'updated_at';
		} else {
			$allowed_columns[] = 'updated_at';
		}

		$filtered = array();

		foreach ($allowed_columns as $column) {
			if (array_key_exists($column, $data)) {
				$filtered[$column] = $data[$column];
			}
		}

		return $filtered;
	}

	/**
	 * Нормализира datetime стойност за DB.
	 *
	 * @param mixed $value Стойност.
	 * @return string|null
	 */
	private static function normalize_datetime_value($value): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}

		return sanitize_text_field((string) $value);
	}

	/**
	 * Генерира wpdb format масив за ред.
	 *
	 * @param array<string,mixed> $data Данни.
	 * @return array<int, string>
	 */
	private static function formats_for_row(array $data): array
	{
		$formats = array();

		foreach ($data as $key => $value) {
			$formats[] = self::format_for_column($key, $value);
		}

		return $formats;
	}

	/**
	 * Връща wpdb format за дадена колона.
	 *
	 * @param string $key Колона.
	 * @param mixed  $value Стойност.
	 * @return string
	 */
	private static function format_for_column(string $key, $value): string
	{
		if ($value === null) {
			return '%s';
		}

		switch ($key) {
			case 'id':
			case 'order_id':
			case 'order_item_id':
			case 'product_id':
			case 'variation_id':
			case 'attempts':
				return '%d';

			default:
				return '%s';
		}
	}

	/**
	 * Санитизира send status.
	 *
	 * @param string $status Статус.
	 * @return string
	 */
	private static function sanitize_send_status(string $status): string
	{
		$status = trim(strtolower($status));

		return in_array($status, Constants::allowed_statuses(), true)
			? $status
			: '';
	}

	/**
	 * Санитизира template source.
	 *
	 * @param string $template_source Source.
	 * @return string
	 */
	private static function sanitize_template_source(string $template_source): string
	{
		$template_source = trim(strtolower($template_source));

		return in_array($template_source, Constants::allowed_template_sources(), true)
			? $template_source
			: '';
	}

	/**
	 * Санитизира trigger source.
	 *
	 * @param string $trigger_source Trigger source.
	 * @return string
	 */
	private static function sanitize_trigger_source(string $trigger_source): string
	{
		$trigger_source = trim(strtolower($trigger_source));

		return in_array($trigger_source, Constants::allowed_trigger_sources(), true)
			? $trigger_source
			: '';
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}