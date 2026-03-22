<?php
/**
 * Централизирани константи за плъгина.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Държи всички вътрешни константи на плъгина.
 */
final class Constants
{
	/**
	 * Версия на DB схемата.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Име на capability за достъп до настройките.
	 */
	public const CAPABILITY_MANAGE = 'manage_woocommerce';

	/**
	 * Основен slug на плъгина.
	 */
	public const PLUGIN_SLUG = 'webdigitech-woo-product-emailer';

	/**
	 * Slug за settings страница.
	 */
	public const MENU_SLUG_SETTINGS = 'wdt-wcpe-settings';

	/**
	 * Slug за logs страница.
	 */
	public const MENU_SLUG_LOGS = 'wdt-wcpe-logs';

	/**
	 * Slug за tools страница.
	 */
	public const MENU_SLUG_TOOLS = 'wdt-wcpe-tools';

	/**
	 * Slug за system status страница.
	 */
	public const MENU_SLUG_SYSTEM_STATUS = 'wdt-wcpe-system-status';

	/**
	 * Option key за версията на базата.
	 */
	public const OPTION_DB_VERSION = 'wdt_wcpe_db_version';

	/**
	 * Option key за глобалните настройки.
	 */
	public const OPTION_SETTINGS = 'wdt_wcpe_settings';

	/**
	 * Option key дали да се пазят данните при uninstall.
	 */
	public const OPTION_PRESERVE_DATA_ON_UNINSTALL = 'wdt_wcpe_preserve_data_on_uninstall';

	/**
	 * Option key за timestamp на последния recovery run.
	 */
	public const OPTION_LAST_RECOVERY_RUN = 'wdt_wcpe_last_recovery_run';

	/**
	 * Option key за timestamp на последния retry run.
	 */
	public const OPTION_LAST_RETRY_RUN = 'wdt_wcpe_last_retry_run';

	/**
	 * Option key за timestamp на последния cleanup run.
	 */
	public const OPTION_LAST_CLEANUP_RUN = 'wdt_wcpe_last_cleanup_run';

	/**
	 * Option key за timestamp на последния успешен test email.
	 */
	public const OPTION_LAST_TEST_EMAIL_SENT_AT = 'wdt_wcpe_last_test_email_sent_at';

	/**
	 * Option key за последна test email грешка.
	 */
	public const OPTION_LAST_TEST_EMAIL_ERROR = 'wdt_wcpe_last_test_email_error';

	/**
	 * Meta key за активиране на custom product email.
	 */
	public const META_ENABLE_CUSTOM_EMAIL = '_wdt_wcpe_enable_custom_email';

	/**
	 * Meta key за активиране на product email логиката.
	 */
	public const META_PRODUCT_EMAIL_ENABLED = '_wdt_wcpe_product_email_enabled';

	/**
	 * Meta key за subject на продукта.
	 */
	public const META_PRODUCT_EMAIL_SUBJECT = '_wdt_wcpe_product_email_subject';

	/**
	 * Meta key за heading на продукта.
	 */
	public const META_PRODUCT_EMAIL_HEADING = '_wdt_wcpe_product_email_heading';

	/**
	 * Meta key за HTML body на продукта.
	 */
	public const META_PRODUCT_EMAIL_BODY_HTML = '_wdt_wcpe_product_email_body_html';

	/**
	 * Meta key за plain text body на продукта.
	 */
	public const META_PRODUCT_EMAIL_BODY_TEXT = '_wdt_wcpe_product_email_body_text';

	/**
	 * Meta key за вътрешни бележки по шаблона.
	 */
	public const META_PRODUCT_EMAIL_NOTES = '_wdt_wcpe_product_email_notes';

	/**
	 * Action name за cron retry job.
	 */
	public const CRON_HOOK_RETRY_FAILED_EMAILS = 'wdt_wcpe_retry_failed_emails';

	/**
	 * Action name за cron recovery scan.
	 */
	public const CRON_HOOK_RECOVERY_SCAN = 'wdt_wcpe_recovery_scan';

	/**
	 * Action name за cron cleanup job.
	 */
	public const CRON_HOOK_CLEANUP_LOGS = 'wdt_wcpe_cleanup_logs';

	/**
	 * Custom cron interval name.
	 */
	public const CRON_SCHEDULE_EVERY_FIFTEEN_MINUTES = 'wdt_wcpe_every_fifteen_minutes';

	/**
	 * AJAX action за test email.
	 */
	public const AJAX_ACTION_TEST_EMAIL = 'wdt_wcpe_send_test_email';

	/**
	 * Template source: product custom.
	 */
	public const TEMPLATE_SOURCE_PRODUCT_CUSTOM = 'product_custom';

	/**
	 * Template source: global fallback.
	 */
	public const TEMPLATE_SOURCE_PLUGIN_FALLBACK = 'plugin_fallback';

	/**
	 * Dispatch status: pending.
	 */
	public const STATUS_PENDING = 'pending';

	/**
	 * Dispatch status: success.
	 */
	public const STATUS_SUCCESS = 'success';

	/**
	 * Dispatch status: failed.
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * Dispatch status: skipped.
	 */
	public const STATUS_SKIPPED = 'skipped';

	/**
	 * Trigger source: payment complete.
	 */
	public const TRIGGER_PAYMENT_COMPLETE = 'woocommerce_payment_complete';

	/**
	 * Trigger source: order status changed.
	 */
	public const TRIGGER_ORDER_STATUS_CHANGED = 'woocommerce_order_status_changed';

	/**
	 * Trigger source: processing.
	 */
	public const TRIGGER_ORDER_STATUS_PROCESSING = 'woocommerce_order_status_processing';

	/**
	 * Trigger source: completed.
	 */
	public const TRIGGER_ORDER_STATUS_COMPLETED = 'woocommerce_order_status_completed';

	/**
	 * Trigger source: cron retry.
	 */
	public const TRIGGER_CRON_RETRY = 'cron_retry';

	/**
	 * Trigger source: cron recovery.
	 */
	public const TRIGGER_CRON_RECOVERY = 'cron_recovery';

	/**
	 * Trigger source: cron cleanup.
	 */
	public const TRIGGER_CRON_CLEANUP = 'cron_cleanup';

	/**
	 * Trigger source: manual test.
	 */
	public const TRIGGER_MANUAL_TEST = 'manual_test';

	/**
	 * Trigger source: manual admin action.
	 */
	public const TRIGGER_MANUAL_ADMIN = 'manual_admin';

	/**
	 * Лимит на batch обработка при recovery.
	 */
	public const RECOVERY_BATCH_LIMIT = 25;

	/**
	 * Лимит на batch обработка при retry.
	 */
	public const RETRY_BATCH_LIMIT = 25;

	/**
	 * Default retention дни за логовете.
	 */
	public const DEFAULT_LOG_RETENTION_DAYS = 30;

	/**
	 * Default max retry attempts.
	 */
	public const DEFAULT_MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Default retry interval в минути.
	 */
	public const DEFAULT_RETRY_INTERVAL_MINUTES = 15;

	/**
	 * Default mail content type.
	 */
	public const DEFAULT_MAIL_CONTENT_TYPE = 'text/html';

	/**
	 * Allowed mail content types.
	 *
	 * @var string[]
	 */
	public const ALLOWED_MAIL_CONTENT_TYPES = array(
		'text/html',
		'text/plain',
	);

	/**
	 * Позволени default order statuses за изпращане.
	 *
	 * WooCommerce статусите тук са без префикс wc-.
	 *
	 * @var string[]
	 */
	public const DEFAULT_ALLOWED_ORDER_STATUSES = array(
		'processing',
		'completed',
	);

	/**
	 * Позволени HTML тагове в email template съдържанието.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_template_html_tags(): array
	{
		return array(
			'a'          => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'br'         => array(),
			'em'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'i'          => array(),
			'u'          => array(),
			'p'          => array(
				'class' => true,
			),
			'div'        => array(
				'class' => true,
			),
			'span'       => array(
				'class' => true,
			),
			'ul'         => array(
				'class' => true,
			),
			'ol'         => array(
				'class' => true,
			),
			'li'         => array(
				'class' => true,
			),
			'blockquote' => array(
				'class' => true,
			),
			'hr'         => array(),
			'h1'         => array(
				'class' => true,
			),
			'h2'         => array(
				'class' => true,
			),
			'h3'         => array(
				'class' => true,
			),
			'h4'         => array(
				'class' => true,
			),
			'h5'         => array(
				'class' => true,
			),
			'h6'         => array(
				'class' => true,
			),
			'table'      => array(
				'class'       => true,
				'border'      => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'width'       => true,
			),
			'thead'      => array(),
			'tbody'      => array(),
			'tfoot'      => array(),
			'tr'         => array(),
			'td'         => array(
				'colspan' => true,
				'rowspan' => true,
				'align'   => true,
				'valign'  => true,
				'width'   => true,
			),
			'th'         => array(
				'colspan' => true,
				'rowspan' => true,
				'align'   => true,
				'valign'  => true,
				'width'   => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
				'title'  => true,
			),
		);
	}

	/**
	 * Placeholder-и, позволени в шаблоните.
	 *
	 * @return string[]
	 */
	public static function allowed_placeholders(): array
	{
		return array(
			'{customer_first_name}',
			'{customer_last_name}',
			'{customer_full_name}',
			'{customer_email}',
			'{order_id}',
			'{order_number}',
			'{order_date}',
			'{product_name}',
			'{product_sku}',
			'{product_id}',
			'{site_name}',
			'{site_url}',
			'{store_name}',
			'{store_email}',
		);
	}

	/**
	 * Връща пълното име на лог таблицата.
	 *
	 * @return string
	 */
	public static function log_table_name(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'wdt_wcpe_email_log';
	}

	/**
	 * Default settings за плъгина.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array
	{
		return array(
			'plugin_enabled'               => 'yes',
			'fallback_email_enabled'       => 'yes',
			'fallback_email_subject'       => '',
			'fallback_email_heading'       => '',
			'fallback_email_body_html'     => '',
			'fallback_email_body_text'     => '',
			'allowed_order_statuses'       => self::DEFAULT_ALLOWED_ORDER_STATUSES,
			'enable_logging'               => 'yes',
			'retain_logs_days'             => self::DEFAULT_LOG_RETENTION_DAYS,
			'retry_failed_sends'           => 'yes',
			'retry_interval_minutes'       => self::DEFAULT_RETRY_INTERVAL_MINUTES,
			'max_retry_attempts'           => self::DEFAULT_MAX_RETRY_ATTEMPTS,
			'test_email_recipient'         => '',
			'mail_content_type_mode'       => self::DEFAULT_MAIL_CONTENT_TYPE,
			'sender_name_override'         => '',
			'sender_email_override'        => '',
			'preserve_data_on_uninstall'   => 'yes',
			'debug_mode'                   => 'no',
			'recovery_enabled'             => 'yes',
			'recovery_lookback_hours'      => 24,
			'recovery_batch_limit'         => self::RECOVERY_BATCH_LIMIT,
			'retry_batch_limit'            => self::RETRY_BATCH_LIMIT,
		);
	}

	/**
	 * Връща разрешените dispatch statuses.
	 *
	 * @return string[]
	 */
	public static function allowed_statuses(): array
	{
		return array(
			self::STATUS_PENDING,
			self::STATUS_SUCCESS,
			self::STATUS_FAILED,
			self::STATUS_SKIPPED,
		);
	}

	/**
	 * Връща разрешените template sources.
	 *
	 * @return string[]
	 */
	public static function allowed_template_sources(): array
	{
		return array(
			self::TEMPLATE_SOURCE_PRODUCT_CUSTOM,
			self::TEMPLATE_SOURCE_PLUGIN_FALLBACK,
		);
	}

	/**
	 * Връща разрешените trigger sources.
	 *
	 * @return string[]
	 */
	public static function allowed_trigger_sources(): array
	{
		return array(
			self::TRIGGER_PAYMENT_COMPLETE,
			self::TRIGGER_ORDER_STATUS_CHANGED,
			self::TRIGGER_ORDER_STATUS_PROCESSING,
			self::TRIGGER_ORDER_STATUS_COMPLETED,
			self::TRIGGER_CRON_RETRY,
			self::TRIGGER_CRON_RECOVERY,
			self::TRIGGER_CRON_CLEANUP,
			self::TRIGGER_MANUAL_TEST,
			self::TRIGGER_MANUAL_ADMIN,
		);
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}