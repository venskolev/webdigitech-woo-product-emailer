<?php
/**
 * Cron orchestration за retry, recovery и cleanup задачите.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Регистрира и управлява cron задачите на плъгина.
 */
final class Cron
{
	/**
	 * Регистрира cron hooks.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_action(Constants::CRON_HOOK_RETRY_FAILED_EMAILS, array(__CLASS__, 'run_retry_failed_emails'));
		add_action(Constants::CRON_HOOK_RECOVERY_SCAN, array(__CLASS__, 'run_recovery_scan'));
		add_action(Constants::CRON_HOOK_CLEANUP_LOGS, array(__CLASS__, 'run_cleanup_logs'));
	}

	/**
	 * Изпълнява retry логиката за неуспешни изпращания.
	 *
	 * @return void
	 */
	public static function run_retry_failed_emails(): void
	{
		update_option(Constants::OPTION_LAST_RETRY_RUN, Helpers::now_mysql(), false);

		if (! Helpers::is_plugin_enabled()) {
			return;
		}

		if (! Helpers::is_retry_enabled()) {
			return;
		}

		if (! class_exists(DispatchRepository::class)) {
			self::debug(
				'Retry cron aborted because DispatchRepository is not available.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RETRY,
				)
			);

			return;
		}

		if (! class_exists(Logger::class)) {
			return;
		}

		if (! class_exists(OrderEmailProcessor::class)) {
			self::debug(
				'Retry cron aborted because OrderEmailProcessor is not available.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RETRY,
				)
			);

			return;
		}

		$max_attempts = Helpers::get_max_retry_attempts();
		$batch_limit  = Helpers::get_retry_batch_limit();

		$rows = DispatchRepository::get_retry_candidates($max_attempts, $batch_limit);

		if ($rows === array()) {
			self::debug(
				'Retry cron found no candidates.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RETRY,
					'max_attempts'   => $max_attempts,
					'batch_limit'    => $batch_limit,
				)
			);

			return;
		}

		foreach ($rows as $row) {
			$record_id    = isset($row['id']) ? (int) $row['id'] : 0;
			$order_id     = isset($row['order_id']) ? (int) $row['order_id'] : 0;
			$dispatch_key = isset($row['dispatch_key']) && is_string($row['dispatch_key']) ? $row['dispatch_key'] : '';

			if ($record_id <= 0 || $order_id <= 0 || $dispatch_key === '') {
				self::debug(
					'Retry cron skipped invalid row payload.',
					array(
						'trigger_source' => Constants::TRIGGER_CRON_RETRY,
						'record_id'      => $record_id,
						'order_id'       => $order_id,
						'dispatch_key'   => $dispatch_key,
					)
				);

				continue;
			}

			try {
				OrderEmailProcessor::process_order($order_id, Constants::TRIGGER_CRON_RETRY);
			} catch (\Throwable $exception) {
				DispatchRepository::mark_failed(
					$record_id,
					$dispatch_key,
					$exception->getMessage(),
					Helpers::get_retry_interval_minutes()
				);

				Logger::log_error(
					'Retry cron processing failed.',
					array(
						'record_id'      => $record_id,
						'dispatch_key'   => $dispatch_key,
						'order_id'       => $order_id,
						'trigger_source' => Constants::TRIGGER_CRON_RETRY,
						'exception'      => $exception->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Изпълнява recovery scan за скорошни eligible поръчки.
	 *
	 * @return void
	 */
	public static function run_recovery_scan(): void
	{
		update_option(Constants::OPTION_LAST_RECOVERY_RUN, Helpers::now_mysql(), false);

		if (! Helpers::is_plugin_enabled()) {
			return;
		}

		if (! Helpers::is_recovery_enabled()) {
			return;
		}

		if (! class_exists('\\WebDigiTech\\WooProductEmailer\\Woo\\OrderQuery')) {
			self::debug(
				'Recovery cron aborted because OrderQuery is not available.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RECOVERY,
				)
			);

			return;
		}

		if (! class_exists(OrderEmailProcessor::class)) {
			self::debug(
				'Recovery cron aborted because OrderEmailProcessor is not available.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RECOVERY,
				)
			);

			return;
		}

		$lookback_hours = Helpers::get_recovery_lookback_hours();
		$batch_limit    = Helpers::get_recovery_batch_limit();

		$order_ids = \WebDigiTech\WooProductEmailer\Woo\OrderQuery::get_recent_paid_order_ids_for_recovery(
			Helpers::get_allowed_order_statuses(),
			$lookback_hours,
			$batch_limit
		);

		if ($order_ids === array()) {
			self::debug(
				'Recovery cron found no recent paid orders.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_RECOVERY,
					'lookback_hours' => $lookback_hours,
					'batch_limit'    => $batch_limit,
				)
			);

			return;
		}

		foreach ($order_ids as $order_id) {
			$order_id = (int) $order_id;

			if ($order_id <= 0) {
				continue;
			}

			try {
				OrderEmailProcessor::process_order($order_id, Constants::TRIGGER_CRON_RECOVERY);
			} catch (\Throwable $exception) {
				if (class_exists(Logger::class)) {
					Logger::log_error(
						'Recovery cron processing failed.',
						array(
							'order_id'       => $order_id,
							'trigger_source' => Constants::TRIGGER_CRON_RECOVERY,
							'exception'      => $exception->getMessage(),
						)
					);
				}
			}
		}
	}

	/**
	 * Изпълнява cleanup на стари логове според retention настройката.
	 *
	 * @return void
	 */
	public static function run_cleanup_logs(): void
	{
		update_option(Constants::OPTION_LAST_CLEANUP_RUN, Helpers::now_mysql(), false);

		if (! Helpers::is_plugin_enabled()) {
			return;
		}

		if (! Helpers::is_logging_enabled()) {
			self::debug(
				'Cleanup cron skipped because logging is disabled.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_CLEANUP,
				)
			);

			return;
		}

		$retention_days = Helpers::get_log_retention_days();

		if ($retention_days <= 0) {
			self::debug(
				'Cleanup cron skipped because retention days are invalid.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_CLEANUP,
					'retention_days' => $retention_days,
				)
			);

			return;
		}

		try {
			$deleted_rows = 0;

			if (class_exists(DispatchRepository::class)
				&& method_exists(DispatchRepository::class, 'delete_logs_older_than_days')) {
				$deleted_rows = (int) DispatchRepository::delete_logs_older_than_days($retention_days);
			} elseif (class_exists(Logger::class)
				&& method_exists(Logger::class, 'purge_old_logs')) {
				$deleted_rows = (int) Logger::purge_old_logs();
			} else {
				self::debug(
					'Cleanup cron aborted because no cleanup method is available.',
					array(
						'trigger_source' => Constants::TRIGGER_CRON_CLEANUP,
						'retention_days' => $retention_days,
					)
				);

				return;
			}

			self::debug(
				'Cleanup cron completed.',
				array(
					'trigger_source' => Constants::TRIGGER_CRON_CLEANUP,
					'retention_days' => $retention_days,
					'deleted_rows'   => $deleted_rows,
				)
			);
		} catch (\Throwable $exception) {
			if (class_exists(Logger::class)) {
				Logger::log_error(
					'Cleanup cron failed.',
					array(
						'trigger_source' => Constants::TRIGGER_CRON_CLEANUP,
						'retention_days' => $retention_days,
						'exception'      => $exception->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Логва debug съобщение само ако Logger е наличен.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	private static function debug(string $message, array $context = array()): void
	{
		if (! class_exists(Logger::class)) {
			return;
		}

		Logger::log_debug($message, $context);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}