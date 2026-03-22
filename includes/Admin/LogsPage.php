<?php
/**
 * Logs страница на плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Admin;

use WebDigiTech\WooProductEmailer\Capabilities;
use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Рендерира admin страницата за логове.
 */
final class LogsPage
{
	/**
	 * Брой записи на страница.
	 */
	private const PER_PAGE = 20;

	/**
	 * Рендерира страницата.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		Capabilities::enforce_manage_capability();

		$filters = self::get_filters();
		$table_name = self::get_logs_table_name();
		$table_exists = self::logs_table_exists($table_name);

		echo '<div class="wrap wdt-wcpe-admin-wrap">';
		echo '<h1>' . esc_html__('Logs', 'webdigitech-woo-product-emailer') . '</h1>';
		echo '<p>' . esc_html__('Review plugin activity, email dispatch attempts, test email events and operational errors stored in the plugin log table.', 'webdigitech-woo-product-emailer') . '</p>';

		if (! $table_exists) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('The log table does not exist yet. Activate or reinstall the plugin setup routine before using the logs screen.', 'webdigitech-woo-product-emailer') . '</p></div>';

			FooterRenderer::render();
			echo '</div>';

			return;
		}

		$counts = self::get_log_counts($table_name);
		$result = self::get_logs($table_name, $filters);

		self::render_overview_cards($counts);
		self::render_filters_form($filters);
		self::render_logs_table($result['rows']);
		self::render_pagination(
			(int) $result['total'],
			(int) $filters['paged'],
			self::PER_PAGE
		);

		FooterRenderer::render();

		echo '</div>';
	}

	/**
	 * Връща таблицата за логове.
	 *
	 * @return string
	 */
	private static function get_logs_table_name(): string
	{
		return Constants::log_table_name();
	}

	/**
	 * Проверява дали таблицата съществува.
	 *
	 * @param string $table_name Име на таблицата.
	 * @return bool
	 */
	private static function logs_table_exists(string $table_name): bool
	{
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
		);

		return is_string($found) && $found === $table_name;
	}

	/**
	 * Чете филтрите от URL.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_filters(): array
	{
		$level = isset($_GET['level']) ? Helpers::sanitize_text(wp_unslash($_GET['level'])) : '';
		$channel = isset($_GET['channel']) ? Helpers::sanitize_text(wp_unslash($_GET['channel'])) : '';
		$search = isset($_GET['s']) ? Helpers::sanitize_text(wp_unslash($_GET['s'])) : '';
		$paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;

		if ($paged < 1) {
			$paged = 1;
		}

		return array(
			'level'   => $level,
			'channel' => $channel,
			'search'  => $search,
			'paged'   => $paged,
		);
	}

	/**
	 * Връща обобщени бройки по основни нива.
	 *
	 * @param string $table_name Име на таблицата.
	 * @return array<string, int>
	 */
	private static function get_log_counts(string $table_name): array
	{
		global $wpdb;

		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
		$errors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE send_status = %s OR last_error IS NOT NULL AND last_error != ''",
				'failed'
			)
		);
		$warnings = 0;
		$info = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE send_status = %s",
				'success'
			)
		);

		return array(
			'total'    => $total,
			'error'    => $errors,
			'warning'  => $warnings,
			'info'     => $info,
		);
	}

	/**
	 * Извлича логовете според филтрите и pagination.
	 *
	 * @param string               $table_name Име на таблицата.
	 * @param array<string, mixed> $filters Филтри.
	 * @return array<string, mixed>
	 */
	private static function get_logs(string $table_name, array $filters): array
	{
		global $wpdb;

		$where = array('1=1');
		$params = array();

		if ($filters['level'] !== '') {
			if ($filters['level'] === 'error') {
				$where[] = "(send_status = %s OR (last_error IS NOT NULL AND last_error != ''))";
				$params[] = 'failed';
			} elseif ($filters['level'] === 'info') {
				$where[] = 'send_status = %s';
				$params[] = 'success';
			}
		}

		if ($filters['channel'] !== '') {
			$like = '%' . $wpdb->esc_like((string) $filters['channel']) . '%';
			$where[] = '(trigger_source LIKE %s OR template_source LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ($filters['search'] !== '') {
			$like = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
			$where[] = '(dispatch_key LIKE %s OR customer_email LIKE %s OR email_subject LIKE %s OR last_error LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode(' AND ', $where);

		$total_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		if (! empty($params)) {
			$total_sql = $wpdb->prepare($total_sql, $params);
		}

		$total = (int) $wpdb->get_var($total_sql);

		$offset = (((int) $filters['paged']) - 1) * self::PER_PAGE;
		if ($offset < 0) {
			$offset = 0;
		}

		$query_params = $params;
		$query_params[] = self::PER_PAGE;
		$query_params[] = $offset;

		$data_sql = "
			SELECT *
			FROM {$table_name}
			WHERE {$where_sql}
			ORDER BY id DESC
			LIMIT %d OFFSET %d
		";

		$data_sql = $wpdb->prepare($data_sql, $query_params);
		$rows = $wpdb->get_results($data_sql, ARRAY_A);

		if (! is_array($rows)) {
			$rows = array();
		}

		return array(
			'total' => $total,
			'rows'  => $rows,
		);
	}

	/**
	 * Рендерира overview карти.
	 *
	 * @param array<string, int> $counts Бройки.
	 * @return void
	 */
	private static function render_overview_cards(array $counts): void
	{
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:18px 0 24px;">';

		self::render_overview_card(
			__('Total Logs', 'webdigitech-woo-product-emailer'),
			(string) $counts['total'],
			__('All stored log entries.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Errors', 'webdigitech-woo-product-emailer'),
			(string) $counts['error'],
			__('Critical failures and hard send errors.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Warnings', 'webdigitech-woo-product-emailer'),
			(string) $counts['warning'],
			__('Soft issues and recoverable events.', 'webdigitech-woo-product-emailer')
		);

		self::render_overview_card(
			__('Info', 'webdigitech-woo-product-emailer'),
			(string) $counts['info'],
			__('Operational and success records.', 'webdigitech-woo-product-emailer')
		);

		echo '</div>';
	}

	/**
	 * Рендерира overview карта.
	 *
	 * @param string $title Заглавие.
	 * @param string $value Стойност.
	 * @param string $description Описание.
	 * @return void
	 */
	private static function render_overview_card(string $title, string $value, string $description): void
	{
		echo '<div class="card" style="padding:16px 18px;">';
		echo '<h2 style="margin:0 0 10px;">' . esc_html($title) . '</h2>';
		echo '<p style="margin:0 0 8px;font-size:16px;font-weight:600;">' . esc_html($value) . '</p>';
		echo '<p style="margin:0;color:#50575e;">' . esc_html($description) . '</p>';
		echo '</div>';
	}

	/**
	 * Рендерира формата за филтри.
	 *
	 * @param array<string, mixed> $filters Филтри.
	 * @return void
	 */
	private static function render_filters_form(array $filters): void
	{
		echo '<div class="card" style="max-width:1100px;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Filters', 'webdigitech-woo-product-emailer') . '</h2>';

		echo '<form method="get" action="">';
		echo '<input type="hidden" name="page" value="' . esc_attr(Constants::MENU_SLUG_LOGS) . '">';

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">';

		echo '<div>';
		echo '<label for="wdt-wcpe-log-level" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__('Level', 'webdigitech-woo-product-emailer') . '</label>';
		echo '<select id="wdt-wcpe-log-level" name="level" class="regular-text">';
		echo '<option value="">' . esc_html__('All levels', 'webdigitech-woo-product-emailer') . '</option>';
		foreach (self::get_level_options() as $value => $label) {
			echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['level'], $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div>';
		echo '<label for="wdt-wcpe-log-channel" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__('Channel', 'webdigitech-woo-product-emailer') . '</label>';
		echo '<input type="text" id="wdt-wcpe-log-channel" name="channel" class="regular-text" value="' . esc_attr((string) $filters['channel']) . '" placeholder="' . esc_attr__('Example: ajax_test_email', 'webdigitech-woo-product-emailer') . '">';
		echo '</div>';

		echo '<div>';
		echo '<label for="wdt-wcpe-log-search" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__('Search', 'webdigitech-woo-product-emailer') . '</label>';
		echo '<input type="text" id="wdt-wcpe-log-search" name="s" class="regular-text" value="' . esc_attr((string) $filters['search']) . '" placeholder="' . esc_attr__('Message or context text', 'webdigitech-woo-product-emailer') . '">';
		echo '</div>';

		echo '<div>';
		submit_button(
			__('Apply Filters', 'webdigitech-woo-product-emailer'),
			'primary',
			'',
			false
		);
		echo ' <a class="button" href="' . esc_url(Helpers::admin_page_url(Constants::MENU_SLUG_LOGS)) . '">' . esc_html__('Reset', 'webdigitech-woo-product-emailer') . '</a>';
		echo '</div>';

		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Рендерира таблицата с логове.
	 *
	 * @param array<int, array<string, mixed>> $rows Редове.
	 * @return void
	 */
	private static function render_logs_table(array $rows): void
	{
		echo '<div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:18px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__('Log Entries', 'webdigitech-woo-product-emailer') . '</h2>';

		if (empty($rows)) {
			echo '<p>' . esc_html__('No log entries found for the current filters.', 'webdigitech-woo-product-emailer') . '</p>';
			echo '</div>';

			return;
		}

		echo '<div style="overflow:auto;">';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th style="min-width:70px;">' . esc_html__('ID', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:120px;">' . esc_html__('Level', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:140px;">' . esc_html__('Channel', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:220px;">' . esc_html__('Dispatch Key', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:220px;">' . esc_html__('Recipient', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:140px;">' . esc_html__('Status', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:280px;">' . esc_html__('Subject', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:280px;">' . esc_html__('Last Error', 'webdigitech-woo-product-emailer') . '</th>';
		echo '<th style="min-width:170px;">' . esc_html__('Created At', 'webdigitech-woo-product-emailer') . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ($rows as $row) {
			$id = isset($row['id']) ? (string) $row['id'] : '';
			$trigger_source = isset($row['trigger_source']) ? (string) $row['trigger_source'] : '';
			$template_source = isset($row['template_source']) ? (string) $row['template_source'] : '';
			$dispatch_key = isset($row['dispatch_key']) ? (string) $row['dispatch_key'] : '';
			$customer_email = isset($row['customer_email']) ? (string) $row['customer_email'] : '';
			$send_status = isset($row['send_status']) ? (string) $row['send_status'] : '';
			$email_subject = isset($row['email_subject']) ? (string) $row['email_subject'] : '';
			$last_error = isset($row['last_error']) ? (string) $row['last_error'] : '';
			$created_at = isset($row['created_at']) ? (string) $row['created_at'] : '';
			$channel = $trigger_source !== '' ? $trigger_source : $template_source;

			echo '<tr>';
			echo '<td>' . esc_html($id) . '</td>';
			echo '<td>' . self::render_level_badge(self::map_status_to_level($send_status, $last_error)) . '</td>';
			echo '<td><code>' . esc_html($channel !== '' ? $channel : '—') . '</code></td>';
			echo '<td><code>' . esc_html($dispatch_key !== '' ? $dispatch_key : '—') . '</code></td>';
			echo '<td>' . esc_html($customer_email !== '' ? $customer_email : '—') . '</td>';
			echo '<td>' . esc_html($send_status !== '' ? $send_status : '—') . '</td>';
			echo '<td>' . esc_html($email_subject !== '' ? $email_subject : '—') . '</td>';
			echo '<td><textarea readonly="readonly" class="large-text code" rows="4" style="min-width:260px;">' . esc_textarea($last_error !== '' ? $last_error : '—') . '</textarea></td>';
			echo '<td>' . esc_html($created_at !== '' ? $created_at : '—') . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Рендерира badge за level.
	 *
	 * @param string $level Ниво.
	 * @return string
	 */
	private static function render_level_badge(string $level): string
	{
		$normalized = strtolower($level);
		$background = '#e5e7eb';
		$color = '#1f2937';

		if ($normalized === 'error') {
			$background = '#fde2e1';
			$color = '#9f1239';
		} elseif ($normalized === 'warning' || $normalized === 'warn') {
			$background = '#fff4d6';
			$color = '#92400e';
		} elseif ($normalized === 'info') {
			$background = '#dbeafe';
			$color = '#1d4ed8';
		} elseif ($normalized === 'debug') {
			$background = '#ede9fe';
			$color = '#5b21b6';
		}

		return '<span style="display:inline-block;padding:4px 8px;border-radius:999px;background:' . esc_attr($background) . ';color:' . esc_attr($color) . ';font-weight:600;">' . esc_html($level !== '' ? strtoupper($level) : '—') . '</span>';
	}

	/**
	 * Картира send status към логическо ниво за UI.
	 *
	 * @param string $send_status Статус.
	 * @param string $last_error Последна грешка.
	 * @return string
	 */
	private static function map_status_to_level(string $send_status, string $last_error): string
	{
		if ($last_error !== '' || $send_status === 'failed') {
			return 'error';
		}

		if ($send_status === 'success') {
			return 'info';
		}

		if ($send_status === 'pending' || $send_status === 'skipped') {
			return 'warning';
		}

		return 'debug';
	}

	/**
	 * Рендерира pagination.
	 *
	 * @param int $total Общо записи.
	 * @param int $current_page Текуща страница.
	 * @param int $per_page Брой на страница.
	 * @return void
	 */
	private static function render_pagination(int $total, int $current_page, int $per_page): void
	{
		$total_pages = (int) ceil($total / $per_page);

		if ($total_pages <= 1) {
			return;
		}

		$base_url = remove_query_arg('paged');
		$links = paginate_links(
			array(
				'base'      => add_query_arg('paged', '%#%', $base_url),
				'format'    => '',
				'current'   => max(1, $current_page),
				'total'     => max(1, $total_pages),
				'type'      => 'array',
				'prev_text' => __('« Previous', 'webdigitech-woo-product-emailer'),
				'next_text' => __('Next »', 'webdigitech-woo-product-emailer'),
			)
		);

		if (! is_array($links) || empty($links)) {
			return;
		}

		echo '<div class="tablenav"><div class="tablenav-pages" style="margin:16px 0 0;">';
		foreach ($links as $link) {
			echo wp_kses_post($link . ' ');
		}
		echo '</div></div>';
	}

	/**
	 * Опции за ниво.
	 *
	 * @return array<string, string>
	 */
	private static function get_level_options(): array
	{
		return array(
			'debug'   => __('Debug', 'webdigitech-woo-product-emailer'),
			'info'    => __('Info', 'webdigitech-woo-product-emailer'),
			'warning' => __('Warning', 'webdigitech-woo-product-emailer'),
			'error'   => __('Error', 'webdigitech-woo-product-emailer'),
		);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}