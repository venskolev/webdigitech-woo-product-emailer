<?php
/**
 * Логиране и audit trail за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Централизиран logger за DB логове и error_log диагностикa.
 */
final class Logger
{
	/**
	 * Записва успешен dispatch лог.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	public static function log_success(array $data): int
	{
		$data['send_status'] = Constants::STATUS_SUCCESS;

		return self::write_dispatch_log($data);
	}

	/**
	 * Записва failed dispatch лог.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	public static function log_failed(array $data): int
	{
		$data['send_status'] = Constants::STATUS_FAILED;

		return self::write_dispatch_log($data);
	}

	/**
	 * Записва skipped dispatch лог.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	public static function log_skip(array $data): int
	{
		$data['send_status'] = Constants::STATUS_SKIPPED;

		return self::write_dispatch_log($data);
	}

	/**
	 * Записва pending dispatch лог.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	public static function log_pending(array $data): int
	{
		$data['send_status'] = Constants::STATUS_PENDING;

		return self::write_dispatch_log($data);
	}

	/**
	 * Логва системна грешка в PHP error log при debug режим.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	public static function log_error(string $message, array $context = array()): void
	{
		self::write_runtime_log('ERROR', $message, $context);
	}

	/**
	 * Логва информационно съобщение при активен debug режим.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	public static function log_info(string $message, array $context = array()): void
	{
		self::write_runtime_log('INFO', $message, $context);
	}

	/**
	 * Логва debug съобщение при активен debug режим.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	public static function log_debug(string $message, array $context = array()): void
	{
		self::write_runtime_log('DEBUG', $message, $context);
	}

	/**
	 * Връща списък с логове за admin страницата.
	 *
	 * @param array<string, mixed> $args Аргументи за филтриране.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_logs(array $args = array()): array
	{
		global $wpdb;

		$table_name = Constants::log_table_name();
		$defaults = array(
			'page'       => 1,
			'per_page'   => 20,
			'status'     => '',
			'order_id'   => 0,
			'product_id' => 0,
			'search'     => '',
		);

		$args = wp_parse_args($args, $defaults);

		$page = Helpers::normalize_int($args['page'], 1, 1, 999999);
		$per_page = Helpers::normalize_int($args['per_page'], 20, 1, 200);
		$offset = ($page - 1) * $per_page;

		$where = array('1=1');
		$values = array();

		if (is_string($args['status']) && $args['status'] !== '' && in_array($args['status'], Constants::allowed_statuses(), true)) {
			$where[] = 'send_status = %s';
			$values[] = $args['status'];
		}

		if (is_numeric($args['order_id']) && (int) $args['order_id'] > 0) {
			$where[] = 'order_id = %d';
			$values[] = (int) $args['order_id'];
		}

		if (is_numeric($args['product_id']) && (int) $args['product_id'] > 0) {
			$where[] = 'product_id = %d';
			$values[] = (int) $args['product_id'];
		}

		if (is_string($args['search']) && trim($args['search']) !== '') {
			$like = '%' . $wpdb->esc_like(trim($args['search'])) . '%';
			$where[] = '(dispatch_key LIKE %s OR customer_email LIKE %s OR template_identifier LIKE %s OR trigger_source LIKE %s OR last_error LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$sql = "SELECT *
			FROM {$table_name}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d';

		$values[] = $per_page;
		$values[] = $offset;

		$prepared = $wpdb->prepare($sql, $values);
		$rows = $wpdb->get_results($prepared, ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Връща общия брой логове за admin pagination.
	 *
	 * @param array<string, mixed> $args Аргументи за филтриране.
	 * @return int
	 */
	public static function count_logs(array $args = array()): int
	{
		global $wpdb;

		$table_name = Constants::log_table_name();
		$defaults = array(
			'status'     => '',
			'order_id'   => 0,
			'product_id' => 0,
			'search'     => '',
		);

		$args = wp_parse_args($args, $defaults);

		$where = array('1=1');
		$values = array();

		if (is_string($args['status']) && $args['status'] !== '' && in_array($args['status'], Constants::allowed_statuses(), true)) {
			$where[] = 'send_status = %s';
			$values[] = $args['status'];
		}

		if (is_numeric($args['order_id']) && (int) $args['order_id'] > 0) {
			$where[] = 'order_id = %d';
			$values[] = (int) $args['order_id'];
		}

		if (is_numeric($args['product_id']) && (int) $args['product_id'] > 0) {
			$where[] = 'product_id = %d';
			$values[] = (int) $args['product_id'];
		}

		if (is_string($args['search']) && trim($args['search']) !== '') {
			$like = '%' . $wpdb->esc_like(trim($args['search'])) . '%';
			$where[] = '(dispatch_key LIKE %s OR customer_email LIKE %s OR template_identifier LIKE %s OR trigger_source LIKE %s OR last_error LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$sql = "SELECT COUNT(id)
			FROM {$table_name}
			WHERE " . implode(' AND ', $where);

		$prepared = $wpdb->prepare($sql, $values);
		$count = $wpdb->get_var($prepared);

		return is_numeric($count) ? (int) $count : 0;
	}

	/**
	 * Изтрива стари логове според retention настройката.
	 *
	 * @return int Брой изтрити записи.
	 */
	public static function purge_old_logs(): int
	{
		global $wpdb;

		$retention_days = Helpers::get_log_retention_days();
		$table_name = Constants::log_table_name();
		$threshold = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s",
				$threshold
			)
		);

		return is_numeric($deleted) ? (int) $deleted : 0;
	}

	/**
	 * Записва runtime лог в PHP error_log при активен debug режим.
	 *
	 * @param string               $level Ниво на лога.
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	private static function write_runtime_log(string $level, string $message, array $context = array()): void
	{
		if (! Helpers::is_debug_enabled()) {
			return;
		}

		$level = strtoupper(trim($level));

		if ($level === '') {
			$level = 'DEBUG';
		}

		$context_json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (! is_string($context_json)) {
			$context_json = '{}';
		}

		error_log(
			sprintf(
				'[WDT_WCPE][%s] %s | Context: %s',
				$level,
				$message,
				$context_json
			)
		);
	}

	/**
	 * Вътрешен запис в dispatch log таблицата.
	 *
	 * @param array<string, mixed> $data Данни за записа.
	 * @return int
	 */
	private static function write_dispatch_log(array $data): int
	{
		global $wpdb;

		if (! Helpers::is_logging_enabled()) {
			return 0;
		}

		$table_name = Constants::log_table_name();

		$dispatch_key = Helpers::sanitize_text((string) Helpers::array_get($data, 'dispatch_key', ''));
		$order_id = (int) Helpers::array_get($data, 'order_id', 0);
		$order_item_id = (int) Helpers::array_get($data, 'order_item_id', 0);
		$product_id = (int) Helpers::array_get($data, 'product_id', 0);
		$variation_id = (int) Helpers::array_get($data, 'variation_id', 0);
		$customer_email = Helpers::sanitize_email_address(Helpers::array_get($data, 'customer_email', ''));
		$template_source = self::sanitize_template_source((string) Helpers::array_get($data, 'template_source', ''));
		$template_identifier = Helpers::sanitize_text((string) Helpers::array_get($data, 'template_identifier', ''));
		$email_subject = Helpers::sanitize_email_subject(Helpers::array_get($data, 'email_subject', ''));
		$email_heading = Helpers::sanitize_text(Helpers::array_get($data, 'email_heading', ''));
		$email_body_html = Helpers::sanitize_template_html(Helpers::array_get($data, 'email_body_html', ''));
		$email_body_text = Helpers::sanitize_template_text(Helpers::array_get($data, 'email_body_text', ''));
		$trigger_source = self::sanitize_trigger_source((string) Helpers::array_get($data, 'trigger_source', ''));
		$send_status = self::sanitize_send_status((string) Helpers::array_get($data, 'send_status', Constants::STATUS_PENDING));
		$attempts = Helpers::normalize_int(Helpers::array_get($data, 'attempts', 0), 0, 0, 9999);
		$last_error = Helpers::sanitize_textarea(Helpers::array_get($data, 'last_error', ''));
		$payload_hash = Helpers::sanitize_text((string) Helpers::array_get($data, 'payload_hash', ''));
		$sent_at = self::sanitize_mysql_datetime_nullable(Helpers::array_get($data, 'sent_at', null));
		$last_attempt_at = self::sanitize_mysql_datetime_nullable(Helpers::array_get($data, 'last_attempt_at', Helpers::now_mysql()));
		$next_retry_at = self::sanitize_mysql_datetime_nullable(Helpers::array_get($data, 'next_retry_at', null));

		if ($dispatch_key === '') {
			return 0;
		}

		$insert_data = array(
			'dispatch_key'        => $dispatch_key,
			'order_id'            => $order_id,
			'order_item_id'       => $order_item_id,
			'product_id'          => $product_id,
			'variation_id'        => $variation_id,
			'customer_email'      => $customer_email,
			'template_source'     => $template_source,
			'template_identifier' => $template_identifier,
			'email_subject'       => $email_subject,
			'email_heading'       => $email_heading,
			'email_body_html'     => $email_body_html,
			'email_body_text'     => $email_body_text,
			'trigger_source'      => $trigger_source,
			'send_status'         => $send_status,
			'attempts'            => $attempts,
			'last_error'          => $last_error,
			'payload_hash'        => $payload_hash,
			'sent_at'             => $sent_at,
			'last_attempt_at'     => $last_attempt_at,
			'next_retry_at'       => $next_retry_at,
		);

		$formats = array(
			'%s',
			'%d',
			'%d',
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		$existing_id = DispatchRepository::find_log_id_by_dispatch_key($dispatch_key);

		if ($existing_id > 0) {
			$updated = $wpdb->update(
				$table_name,
				$insert_data,
				array(
					'id' => $existing_id,
				),
				$formats,
				array('%d')
			);

			if ($updated === false) {
				self::log_error(
					'Failed to update dispatch log entry.',
					array(
						'dispatch_key' => $dispatch_key,
						'db_error'     => $wpdb->last_error,
					)
				);

				return 0;
			}

			return $existing_id;
		}

		$inserted = $wpdb->insert($table_name, $insert_data, $formats);

		if ($inserted === false) {
			self::log_error(
				'Failed to insert dispatch log entry.',
				array(
					'dispatch_key' => $dispatch_key,
					'db_error'     => $wpdb->last_error,
				)
			);

			return 0;
		}

		return (int) $wpdb->insert_id;
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
			: Constants::STATUS_PENDING;
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
	 * @param string $trigger_source Trigger.
	 * @return string
	 */
	private static function sanitize_trigger_source(string $trigger_source): string
	{
		$trigger_source = trim($trigger_source);

		return in_array($trigger_source, Constants::allowed_trigger_sources(), true)
			? $trigger_source
			: '';
	}

	/**
	 * Валидира nullable MySQL datetime.
	 *
	 * @param mixed $value Стойност.
	 * @return string|null
	 */
	private static function sanitize_mysql_datetime_nullable(mixed $value): ?string
	{
		if (! is_string($value)) {
			return null;
		}

		$value = trim($value);

		if ($value === '') {
			return null;
		}

		$is_valid = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1;

		return $is_valid ? $value : null;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}