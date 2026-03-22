<?php
/**
 * Главен processor за order-based product email изпращане.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Обработва поръчка и изпраща product email-и по order item.
 */
final class OrderEmailProcessor
{
	/**
	 * Обработва поръчка по централизирана логика.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $trigger_source Източник на задействане.
	 * @return array<string, mixed>
	 */
	public static function process_order(int $order_id, string $trigger_source): array
	{
		$order_id       = max(0, $order_id);
		$trigger_source = self::sanitize_trigger_source($trigger_source);

		if ($order_id <= 0) {
			return self::result(
				false,
				array(),
				array(
					'error' => 'Invalid order ID.',
				)
			);
		}

		$order = wc_get_order($order_id);

		if (! $order instanceof \WC_Order) {
			return self::result(
				false,
				array(),
				array(
					'error'    => 'Order could not be loaded.',
					'order_id' => $order_id,
				)
			);
		}

		$order_eligibility = Eligibility::check_order($order);

		if (! (bool) Helpers::array_get($order_eligibility, 'eligible', false)) {
			Logger::log_debug(
				'Order is not eligible for product email processing.',
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
					'reason'         => (string) Helpers::array_get($order_eligibility, 'reason', ''),
					'context'        => Helpers::array_get($order_eligibility, 'context', array()),
				)
			);

			return self::result(
				false,
				array(),
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
					'eligibility'    => $order_eligibility,
				)
			);
		}

		$items = $order->get_items('line_item');

		if (! is_array($items) || $items === array()) {
			Logger::log_debug(
				'Order has no line items to process.',
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
				)
			);

			return self::result(
				true,
				array(),
				array(
					'order_id'       => $order_id,
					'trigger_source' => $trigger_source,
					'message'        => 'Order has no line items.',
				)
			);
		}

		$results = array();

		foreach ($items as $item) {
			if (! $item instanceof \WC_Order_Item_Product) {
				continue;
			}

			$results[] = self::process_order_item($order, $item, $trigger_source);
		}

		return self::result(true, $results, array());
	}

	/**
	 * Обработва единичен order item.
	 *
	 * @param \WC_Order              $order Woo order.
	 * @param \WC_Order_Item_Product $item Woo order item.
	 * @param string                 $trigger_source Trigger source.
	 * @return array<string, mixed>
	 */
	public static function process_order_item(
		\WC_Order $order,
		\WC_Order_Item_Product $item,
		string $trigger_source
	): array {
		$trigger_source = self::sanitize_trigger_source($trigger_source);

		$eligibility = Eligibility::check_order_item($order, $item);
		$is_eligible = (bool) Helpers::array_get($eligibility, 'eligible', false);
		$reason      = (string) Helpers::array_get($eligibility, 'reason', '');
		$context     = Helpers::array_get($eligibility, 'context', array());

		$order_id       = (int) $order->get_id();
		$order_item_id  = (int) $item->get_id();
		$product_id     = (int) $item->get_product_id();
		$variation_id   = (int) $item->get_variation_id();
		$dispatch_key   = Helpers::build_dispatch_key($order_id, $order_item_id, $product_id);
		$customer_email = Helpers::sanitize_email_address((string) $order->get_billing_email());

		if (DispatchRepository::was_sent_successfully($dispatch_key)) {
			Logger::log_debug(
				'Dispatch already sent successfully. Skipping duplicate send.',
				array(
					'order_id'       => $order_id,
					'order_item_id'  => $order_item_id,
					'product_id'     => $product_id,
					'variation_id'   => $variation_id,
					'dispatch_key'   => $dispatch_key,
					'trigger_source' => $trigger_source,
				)
			);

			return array(
				'order_id'       => $order_id,
				'order_item_id'  => $order_item_id,
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'dispatch_key'   => $dispatch_key,
				'status'         => Constants::STATUS_SUCCESS,
				'reason'         => 'already_sent',
			);
		}

		if (! $is_eligible) {
			$record_id = DispatchRepository::ensure_dispatch_record(
				array(
					'dispatch_key'        => $dispatch_key,
					'order_id'            => $order_id,
					'order_item_id'       => $order_item_id,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'customer_email'      => $customer_email,
					'template_source'     => (string) Helpers::array_get($context, 'template_source', ''),
					'template_identifier' => (string) Helpers::array_get($context, 'template_identifier', ''),
					'trigger_source'      => $trigger_source,
					'last_error'          => $reason,
				)
			);

			if ($record_id > 0) {
				DispatchRepository::mark_skipped($record_id, $dispatch_key, self::build_skip_reason($reason, $context));
			}

			Logger::log_debug(
				'Order item skipped during eligibility phase.',
				array(
					'order_id'       => $order_id,
					'order_item_id'  => $order_item_id,
					'product_id'     => $product_id,
					'variation_id'   => $variation_id,
					'dispatch_key'   => $dispatch_key,
					'trigger_source' => $trigger_source,
					'reason'         => $reason,
					'context'        => $context,
				)
			);

			return array(
				'order_id'       => $order_id,
				'order_item_id'  => $order_item_id,
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'dispatch_key'   => $dispatch_key,
				'status'         => Constants::STATUS_SKIPPED,
				'reason'         => $reason,
			);
		}

		$template = Helpers::array_get($context, 'template', array());

		if (! is_array($template) || ! TemplateResolver::is_valid_template($template)) {
			$record_id = DispatchRepository::ensure_dispatch_record(
				array(
					'dispatch_key'        => $dispatch_key,
					'order_id'            => $order_id,
					'order_item_id'       => $order_item_id,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'customer_email'      => $customer_email,
					'template_source'     => (string) Helpers::array_get($context, 'template_source', ''),
					'template_identifier' => (string) Helpers::array_get($context, 'template_identifier', ''),
					'trigger_source'      => $trigger_source,
					'last_error'          => 'Resolved template is invalid.',
				)
			);

			if ($record_id > 0) {
				DispatchRepository::mark_skipped($record_id, $dispatch_key, 'Resolved template is invalid.');
			}

			Logger::log_debug(
				'Resolved template is invalid before placeholder replacement.',
				array(
					'order_id'       => $order_id,
					'order_item_id'  => $order_item_id,
					'product_id'     => $product_id,
					'variation_id'   => $variation_id,
					'dispatch_key'   => $dispatch_key,
					'trigger_source' => $trigger_source,
				)
			);

			return array(
				'order_id'       => $order_id,
				'order_item_id'  => $order_item_id,
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'dispatch_key'   => $dispatch_key,
				'status'         => Constants::STATUS_SKIPPED,
				'reason'         => 'resolved_template_invalid',
			);
		}

		$resolved_template = PlaceholderResolver::resolve_template($template, $order, $item);

		return self::dispatch_email_payload(
			array(
				'trigger_source'      => $trigger_source,
				'mode'                => 'order_item',
				'to'                  => $customer_email,
				'resolved_template'   => $resolved_template,
				'template_source'     => (string) Helpers::array_get($context, 'template_source', ''),
				'template_identifier' => (string) Helpers::array_get($context, 'template_identifier', ''),
				'dispatch_key'        => $dispatch_key,
				'order_id'            => $order_id,
				'order_item_id'       => $order_item_id,
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
			)
		);
	}

	/**
	 * Обработва тестов email през същия централен dispatch pipeline.
	 *
	 * @param array<string, mixed> $payload Test payload.
	 * @param string               $trigger_source Trigger source.
	 * @return array<string, mixed>
	 */
	public static function process_test_email(array $payload, string $trigger_source = Constants::TRIGGER_MANUAL_TEST): array
	{
		$trigger_source = self::sanitize_trigger_source($trigger_source);
		$recipient      = Helpers::sanitize_email_address((string) Helpers::array_get($payload, 'to', ''));

		if ($recipient === '') {
			return array(
				'status' => Constants::STATUS_FAILED,
				'reason' => 'invalid_test_recipient',
				'error'  => 'Invalid test recipient email address.',
			);
		}

		$template = self::build_synthetic_template_from_payload($payload);

		if (! TemplateResolver::is_valid_template($template)) {
			return array(
				'status' => Constants::STATUS_SKIPPED,
				'reason' => 'resolved_template_invalid',
				'error'  => 'Resolved template is invalid.',
			);
		}

		$placeholder_context = self::build_synthetic_placeholder_context($recipient, $payload);
		$resolved_template   = self::resolve_synthetic_template($template, $placeholder_context);

		$result = self::dispatch_email_payload(
			array(
				'trigger_source'      => $trigger_source,
				'mode'                => 'test_email',
				'to'                  => $recipient,
				'resolved_template'   => $resolved_template,
				'template_source'     => 'test_email',
				'template_identifier' => 'synthetic_test_email',
				'dispatch_key'        => '',
				'order_id'            => 0,
				'order_item_id'       => 0,
				'product_id'          => 0,
				'variation_id'        => 0,
			)
		);

		$sent = (string) Helpers::array_get($result, 'status', '') === Constants::STATUS_SUCCESS;

		if ($sent) {
			update_option(Constants::OPTION_LAST_TEST_EMAIL_SENT_AT, Helpers::now_mysql(), false);
			update_option(Constants::OPTION_LAST_TEST_EMAIL_ERROR, '', false);
		} else {
			$error_message = (string) Helpers::array_get($result, 'reason', 'Unknown test email error.');
			update_option(
				Constants::OPTION_LAST_TEST_EMAIL_ERROR,
				Helpers::sanitize_textarea($error_message),
				false
			);
		}

		return $result;
	}

	/**
	 * Централен dispatch pipeline за вече resolved template.
	 *
	 * @param array<string, mixed> $args Dispatch аргументи.
	 * @return array<string, mixed>
	 */
	private static function dispatch_email_payload(array $args): array
	{
		$trigger_source      = self::sanitize_trigger_source((string) Helpers::array_get($args, 'trigger_source', Constants::TRIGGER_MANUAL_ADMIN));
		$mode                = sanitize_key((string) Helpers::array_get($args, 'mode', 'generic'));
		$to                  = Helpers::sanitize_email_address((string) Helpers::array_get($args, 'to', ''));
		$template_source     = Helpers::sanitize_text((string) Helpers::array_get($args, 'template_source', ''));
		$template_identifier = Helpers::sanitize_text((string) Helpers::array_get($args, 'template_identifier', ''));
		$dispatch_key        = Helpers::sanitize_text((string) Helpers::array_get($args, 'dispatch_key', ''));
		$order_id            = (int) Helpers::array_get($args, 'order_id', 0);
		$order_item_id       = (int) Helpers::array_get($args, 'order_item_id', 0);
		$product_id          = (int) Helpers::array_get($args, 'product_id', 0);
		$variation_id        = (int) Helpers::array_get($args, 'variation_id', 0);
		$resolved_template   = Helpers::array_get($args, 'resolved_template', array());

		if (! is_array($resolved_template) || ! TemplateResolver::is_valid_template($resolved_template)) {
			$skip_reason = 'Resolved template became invalid after placeholder replacement.';

			if ($dispatch_key !== '') {
				$record_id = DispatchRepository::ensure_dispatch_record(
					array(
						'dispatch_key'        => $dispatch_key,
						'order_id'            => $order_id,
						'order_item_id'       => $order_item_id,
						'product_id'          => $product_id,
						'variation_id'        => $variation_id,
						'customer_email'      => $to,
						'template_source'     => $template_source,
						'template_identifier' => $template_identifier,
						'trigger_source'      => $trigger_source,
						'last_error'          => $skip_reason,
					)
				);

				if ($record_id > 0) {
					DispatchRepository::mark_skipped($record_id, $dispatch_key, $skip_reason);
				}
			}

			Logger::log_debug(
				'Resolved template became invalid after placeholder replacement.',
				array(
					'mode'            => $mode,
					'order_id'        => $order_id,
					'order_item_id'   => $order_item_id,
					'product_id'      => $product_id,
					'variation_id'    => $variation_id,
					'dispatch_key'    => $dispatch_key,
					'trigger_source'  => $trigger_source,
					'template_source' => $template_source,
				)
			);

			return array(
				'order_id'            => $order_id,
				'order_item_id'       => $order_item_id,
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
				'dispatch_key'        => $dispatch_key,
				'status'              => Constants::STATUS_SKIPPED,
				'reason'              => 'resolved_template_empty_after_placeholders',
				'mode'                => $mode,
				'template_source'     => $template_source,
				'template_identifier' => $template_identifier,
			);
		}

		$record_id = 0;

		if ($dispatch_key !== '') {
			$record_id = DispatchRepository::ensure_dispatch_record(
				array(
					'dispatch_key'        => $dispatch_key,
					'order_id'            => $order_id,
					'order_item_id'       => $order_item_id,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'customer_email'      => $to,
					'template_source'     => $template_source,
					'template_identifier' => $template_identifier,
					'trigger_source'      => $trigger_source,
					'email_subject'       => (string) Helpers::array_get($resolved_template, 'subject', ''),
					'email_heading'       => (string) Helpers::array_get($resolved_template, 'heading', ''),
					'email_body_html'     => (string) Helpers::array_get($resolved_template, 'body_html', ''),
					'email_body_text'     => (string) Helpers::array_get($resolved_template, 'body_text', ''),
					'payload_hash'        => Helpers::build_payload_hash($resolved_template),
				)
			);

			if ($record_id <= 0) {
				Logger::log_error(
					'Failed to create or update dispatch record before sending email.',
					array(
						'mode'            => $mode,
						'order_id'        => $order_id,
						'order_item_id'   => $order_item_id,
						'product_id'      => $product_id,
						'variation_id'    => $variation_id,
						'dispatch_key'    => $dispatch_key,
						'trigger_source'  => $trigger_source,
						'template_source' => $template_source,
					)
				);

				return array(
					'order_id'            => $order_id,
					'order_item_id'       => $order_item_id,
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'dispatch_key'        => $dispatch_key,
					'status'              => Constants::STATUS_FAILED,
					'reason'              => 'dispatch_record_creation_failed',
					'mode'                => $mode,
					'template_source'     => $template_source,
					'template_identifier' => $template_identifier,
				);
			}
		}

		$mail_result = Mailer::send(
			array(
				'to'        => $to,
				'subject'   => (string) Helpers::array_get($resolved_template, 'subject', ''),
				'body_html' => (string) Helpers::array_get($resolved_template, 'body_html', ''),
				'body_text' => (string) Helpers::array_get($resolved_template, 'body_text', ''),
			)
		);

		$send_success = (bool) Helpers::array_get($mail_result, 'success', false);

		if ($send_success) {
			if ($record_id > 0 && $dispatch_key !== '') {
				DispatchRepository::mark_sent(
					$record_id,
					$dispatch_key,
					array(
						'email_subject'   => (string) Helpers::array_get($resolved_template, 'subject', ''),
						'email_heading'   => (string) Helpers::array_get($resolved_template, 'heading', ''),
						'email_body_html' => (string) Helpers::array_get($resolved_template, 'body_html', ''),
						'email_body_text' => (string) Helpers::array_get($resolved_template, 'body_text', ''),
						'payload_hash'    => Helpers::build_payload_hash($resolved_template),
						'trigger_source'  => $trigger_source,
					)
				);
			}

			Logger::log_debug(
				'Product email sent successfully.',
				array(
					'mode'            => $mode,
					'order_id'        => $order_id,
					'order_item_id'   => $order_item_id,
					'product_id'      => $product_id,
					'variation_id'    => $variation_id,
					'dispatch_key'    => $dispatch_key,
					'trigger_source'  => $trigger_source,
					'template_source' => $template_source,
				)
			);

			return array(
				'order_id'            => $order_id,
				'order_item_id'       => $order_item_id,
				'product_id'          => $product_id,
				'variation_id'        => $variation_id,
				'dispatch_key'        => $dispatch_key,
				'status'              => Constants::STATUS_SUCCESS,
				'reason'              => 'sent',
				'mode'                => $mode,
				'template_source'     => $template_source,
				'template_identifier' => $template_identifier,
				'mail_result'         => $mail_result,
			);
		}

		$error_message = (string) Helpers::array_get($mail_result, 'error', 'Unknown mail sending error.');

		if ($record_id > 0 && $dispatch_key !== '') {
			DispatchRepository::mark_failed(
				$record_id,
				$dispatch_key,
				$error_message,
				Helpers::get_retry_interval_minutes()
			);
		}

		Logger::log_error(
			'Product email sending failed.',
			array(
				'mode'            => $mode,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'product_id'      => $product_id,
				'variation_id'    => $variation_id,
				'dispatch_key'    => $dispatch_key,
				'trigger_source'  => $trigger_source,
				'template_source' => $template_source,
				'error'           => $error_message,
			)
		);

		return array(
			'order_id'            => $order_id,
			'order_item_id'       => $order_item_id,
			'product_id'          => $product_id,
			'variation_id'        => $variation_id,
			'dispatch_key'        => $dispatch_key,
			'status'              => Constants::STATUS_FAILED,
			'reason'              => $error_message,
			'mode'                => $mode,
			'template_source'     => $template_source,
			'template_identifier' => $template_identifier,
			'mail_result'         => $mail_result,
		);
	}

	/**
	 * Сглобява synthetic template от test/admin payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, string>
	 */
	private static function build_synthetic_template_from_payload(array $payload): array
	{
		$subject   = Helpers::sanitize_email_subject((string) Helpers::array_get($payload, 'subject', ''));
		$heading   = Helpers::sanitize_text((string) Helpers::array_get($payload, 'heading', ''));
		$body_html = Helpers::sanitize_template_html((string) Helpers::array_get($payload, 'body_html', ''));
		$body_text = Helpers::sanitize_template_text((string) Helpers::array_get($payload, 'body_text', ''));

		if ($body_text === '' && $body_html !== '') {
			$body_text = Helpers::html_to_text($body_html);
		}

		return array(
			'subject'   => $subject,
			'heading'   => $heading,
			'body_html' => $body_html,
			'body_text' => $body_text,
		);
	}

	/**
	 * Сглобява synthetic placeholder context за test email.
	 *
	 * @param string               $recipient Recipient.
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, string>
	 */
	private static function build_synthetic_placeholder_context(string $recipient, array $payload): array
	{
		return array(
			'customer_first_name' => Helpers::sanitize_text((string) Helpers::array_get($payload, 'customer_first_name', __('Test', 'webdigitech-woo-product-emailer'))),
			'customer_last_name'  => Helpers::sanitize_text((string) Helpers::array_get($payload, 'customer_last_name', __('User', 'webdigitech-woo-product-emailer'))),
			'customer_email'      => $recipient,
			'order_id'            => Helpers::sanitize_text((string) Helpers::array_get($payload, 'order_id', '999999')),
			'order_number'        => Helpers::sanitize_text((string) Helpers::array_get($payload, 'order_number', '999999')),
			'order_date'          => Helpers::sanitize_text((string) Helpers::array_get($payload, 'order_date', wp_date(get_option('date_format') . ' ' . get_option('time_format')))),
			'product_id'          => Helpers::sanitize_text((string) Helpers::array_get($payload, 'product_id', '999999')),
			'product_name'        => Helpers::sanitize_text((string) Helpers::array_get($payload, 'product_name', __('Test Product', 'webdigitech-woo-product-emailer'))),
			'product_sku'         => Helpers::sanitize_text((string) Helpers::array_get($payload, 'product_sku', 'TEST-SKU')),
		);
	}

	/**
	 * Resolve-ва synthetic template чрез общия placeholder resolver.
	 *
	 * @param array<string, string> $template Template.
	 * @param array<string, string> $placeholder_context Placeholder контекст.
	 * @return array<string, string>
	 */
	private static function resolve_synthetic_template(array $template, array $placeholder_context): array
	{
		$placeholders = PlaceholderResolver::build_test_placeholders($placeholder_context);

		$subject = PlaceholderResolver::replace_placeholders(
			Helpers::sanitize_email_subject((string) Helpers::array_get($template, 'subject', '')),
			$placeholders
		);

		$heading = PlaceholderResolver::replace_placeholders(
			Helpers::sanitize_text((string) Helpers::array_get($template, 'heading', '')),
			$placeholders
		);

		$body_html = PlaceholderResolver::replace_placeholders(
			Helpers::sanitize_template_html((string) Helpers::array_get($template, 'body_html', '')),
			$placeholders
		);

		$body_text = PlaceholderResolver::replace_placeholders(
			Helpers::sanitize_template_text((string) Helpers::array_get($template, 'body_text', '')),
			$placeholders
		);

		if ($body_text === '' && $body_html !== '') {
			$body_text = Helpers::html_to_text($body_html);
		}

		return array(
			'subject'   => $subject,
			'heading'   => $heading,
			'body_html' => $body_html,
			'body_text' => $body_text,
		);
	}

	/**
	 * Стандартизиран резултат за processor-а.
	 *
	 * @param bool                 $ok OK статус.
	 * @param array<int, mixed>    $items Резултати по items.
	 * @param array<string, mixed> $meta Meta данни.
	 * @return array<string, mixed>
	 */
	private static function result(bool $ok, array $items, array $meta): array
	{
		return array(
			'ok'    => $ok,
			'items' => $items,
			'meta'  => $meta,
		);
	}

	/**
	 * Подготвя детайлна причина за skip лог.
	 *
	 * @param string               $reason Причина.
	 * @param array<string, mixed> $context Контекст.
	 * @return string
	 */
	private static function build_skip_reason(string $reason, array $context): string
	{
		$reason = sanitize_key($reason);

		if ($reason === 'template_unavailable') {
			$template_error = Helpers::sanitize_textarea((string) Helpers::array_get($context, 'template_error', ''));

			if ($template_error !== '') {
				return 'Template unavailable: ' . $template_error;
			}
		}

		if ($reason === 'order_status_not_allowed') {
			$status = Helpers::sanitize_text((string) Helpers::array_get($context, 'status', ''));

			if ($status !== '') {
				return 'Order status not allowed: ' . $status;
			}
		}

		return $reason !== '' ? $reason : 'unknown_skip_reason';
	}

	/**
	 * Санитизира trigger source.
	 *
	 * @param string $trigger_source Trigger source.
	 * @return string
	 */
	private static function sanitize_trigger_source(string $trigger_source): string
	{
		$trigger_source = trim($trigger_source);

		return in_array($trigger_source, Constants::allowed_trigger_sources(), true)
			? $trigger_source
			: Constants::TRIGGER_MANUAL_ADMIN;
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}