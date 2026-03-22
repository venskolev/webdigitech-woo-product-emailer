<?php
/**
 * Рендерира custom product email полетата в WooCommerce product editor.
 *
 * @package WebDigiTechWooProductEmailer
 */

declare(strict_types=1);

namespace WebDigiTech\WooProductEmailer\Product;

use WebDigiTech\WooProductEmailer\Constants;
use WebDigiTech\WooProductEmailer\Helpers;
use WebDigiTech\WooProductEmailer\Logger;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Отговаря само за визуализацията на полетата в product editor-а.
 *
 * Този клас:
 * - добавя custom Product Data tab;
 * - рендерира полетата за имейл шаблон;
 * - показва helper информация и placeholders;
 * - визуализира live статус спрямо текущото състояние на формата;
 * - НЕ записва meta данните (това е работа на ProductEmailMeta).
 */
final class ProductEmailFields
{
	/**
	 * ID на Woo product data tab-а.
	 */
	private const TAB_ID = 'wdt_wcpe_product_email';

	/**
	 * CSS class за таб панела.
	 */
	private const PANEL_CLASS = 'wdt-wcpe-product-email-panel';

	/**
	 * DOM id за status text елемента.
	 */
	private const STATUS_TEXT_ID = 'wdt-wcpe-template-status-text';

	/**
	 * Регистрира всички hooks за рендериране на полетата.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		if (! is_admin()) {
			return;
		}

		if (! class_exists('\\WooCommerce')) {
			return;
		}

		add_filter('woocommerce_product_data_tabs', array(__CLASS__, 'register_product_data_tab'), 50, 1);
		add_action('woocommerce_product_data_panels', array(__CLASS__, 'render_product_data_panel'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_editor_assets'));
	}

	/**
	 * Добавя custom таб в Product Data секцията.
	 *
	 * @param array<string, array<string, mixed>> $tabs Съществуващите tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_product_data_tab(array $tabs): array
	{
		$tabs[self::TAB_ID] = array(
			'label'    => esc_html__('Product Email', 'webdigitech-woo-product-emailer'),
			'target'   => self::TAB_ID . '_panel',
			'class'    => array('show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped'),
			'priority' => 85,
		);

		return $tabs;
	}

	/**
	 * Рендерира панела с custom email полетата.
	 *
	 * @return void
	 */
	public static function render_product_data_panel(): void
	{
		global $post;

		if (! $post instanceof \WP_Post) {
			return;
		}

		$product_id = (int) $post->ID;

		if ($product_id <= 0) {
			return;
		}

		$values = self::get_field_values($product_id);

		echo '<div id="' . esc_attr(self::TAB_ID . '_panel') . '" class="panel woocommerce_options_panel ' . esc_attr(self::PANEL_CLASS) . '">';

		wp_nonce_field('wdt_wcpe_save_product_email', 'wdt_wcpe_product_email_nonce');

		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<strong>' . esc_html__('Product-specific customer email template', 'webdigitech-woo-product-emailer') . '</strong><br />';
		echo '<span class="description">';
		echo esc_html__(
			'Configure a custom email template for this product. When custom email is disabled, the plugin will use the global fallback template if available.',
			'webdigitech-woo-product-emailer'
		);
		echo '</span>';
		echo '</p>';
		echo '</div>';

		self::render_checkbox_field(
			Constants::META_ENABLE_CUSTOM_EMAIL,
			esc_html__('Enable a dedicated email template for this product.', 'webdigitech-woo-product-emailer'),
			$values[Constants::META_ENABLE_CUSTOM_EMAIL],
			''
		);

		self::render_text_field(
			Constants::META_PRODUCT_EMAIL_SUBJECT,
			esc_html__('Email subject', 'webdigitech-woo-product-emailer'),
			(string) $values[Constants::META_PRODUCT_EMAIL_SUBJECT],
			esc_html__(
				'Example: Your download for {product_name} is ready',
				'webdigitech-woo-product-emailer'
			)
		);

		self::render_text_field(
			Constants::META_PRODUCT_EMAIL_HEADING,
			esc_html__('Email heading', 'webdigitech-woo-product-emailer'),
			(string) $values[Constants::META_PRODUCT_EMAIL_HEADING],
			esc_html__(
				'Optional heading used inside the email template.',
				'webdigitech-woo-product-emailer'
			)
		);

		self::render_html_editor_field(
			Constants::META_PRODUCT_EMAIL_BODY_HTML,
			esc_html__('Email HTML body', 'webdigitech-woo-product-emailer'),
			(string) $values[Constants::META_PRODUCT_EMAIL_BODY_HTML],
			esc_html__(
				'Rich HTML version of the customer email. Allowed HTML is sanitized on save.',
				'webdigitech-woo-product-emailer'
			)
		);

		self::render_textarea_field(
			Constants::META_PRODUCT_EMAIL_BODY_TEXT,
			esc_html__('Email text body', 'webdigitech-woo-product-emailer'),
			(string) $values[Constants::META_PRODUCT_EMAIL_BODY_TEXT],
			esc_html__(
				'Plain text fallback version. If left empty and HTML exists, text may be generated automatically.',
				'webdigitech-woo-product-emailer'
			),
			8
		);

		self::render_textarea_field(
			Constants::META_PRODUCT_EMAIL_NOTES,
			esc_html__('Internal notes', 'webdigitech-woo-product-emailer'),
			(string) $values[Constants::META_PRODUCT_EMAIL_NOTES],
			esc_html__(
				'Internal notes for administrators only. This field is never sent to the customer.',
				'webdigitech-woo-product-emailer'
			),
			5
		);

		self::render_placeholder_help_box();
		self::render_template_status_box($values);
		self::render_live_status_script();

		echo '</div>';
	}

	/**
	 * Зарежда базови editor assets при product edit екрана.
	 *
	 * @param string $hook_suffix Текущият admin hook suffix.
	 * @return void
	 */
	public static function enqueue_editor_assets(string $hook_suffix): void
	{
		if (! in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (! $screen instanceof \WP_Screen) {
			return;
		}

		if ($screen->post_type !== 'product') {
			return;
		}

		wp_enqueue_editor();
	}

	/**
	 * Връща текущите стойности за полетата.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private static function get_field_values(int $product_id): array
	{
		return array(
			Constants::META_ENABLE_CUSTOM_EMAIL     => Helpers::sanitize_yes_no(
				get_post_meta($product_id, Constants::META_ENABLE_CUSTOM_EMAIL, true),
				'no'
			),
			Constants::META_PRODUCT_EMAIL_SUBJECT   => Helpers::sanitize_email_subject(
				get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_SUBJECT, true)
			),
			Constants::META_PRODUCT_EMAIL_HEADING   => Helpers::sanitize_text(
				get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_HEADING, true)
			),
			Constants::META_PRODUCT_EMAIL_BODY_HTML => Helpers::sanitize_template_html(
				get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_BODY_HTML, true)
			),
			Constants::META_PRODUCT_EMAIL_BODY_TEXT => Helpers::sanitize_template_text(
				get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_BODY_TEXT, true)
			),
			Constants::META_PRODUCT_EMAIL_NOTES     => Helpers::sanitize_textarea(
				get_post_meta($product_id, Constants::META_PRODUCT_EMAIL_NOTES, true)
			),
		);
	}

	/**
	 * Рендерира checkbox поле.
	 *
	 * @param string $field_id Meta key / field ID.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_checkbox_field(
		string $field_id,
		string $label,
		string $value,
		string $description
	): void {
		if (! function_exists('woocommerce_wp_checkbox')) {
			return;
		}

		$args = array(
			'id'            => $field_id,
			'label'         => $label,
			'value'         => $value === 'yes' ? 'yes' : 'no',
			'cbvalue'       => 'yes',
			'desc_tip'      => false,
			'wrapper_class' => 'show_if_simple show_if_variable show_if_external show_if_grouped',
		);

		if ($description !== '') {
			$args['description'] = $description;
		}

		woocommerce_wp_checkbox($args);
	}

	/**
	 * Рендерира текстово поле.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_text_field(
		string $field_id,
		string $label,
		string $value,
		string $description
	): void {
		if (! function_exists('woocommerce_wp_text_input')) {
			return;
		}

		woocommerce_wp_text_input(
			array(
				'id'            => $field_id,
				'label'         => $label,
				'value'         => $value,
				'description'   => $description,
				'desc_tip'      => false,
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Рендерира textarea поле.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param string $description Description.
	 * @param int    $rows Rows count.
	 * @return void
	 */
	private static function render_textarea_field(
		string $field_id,
		string $label,
		string $value,
		string $description,
		int $rows = 6
	): void {
		if (! function_exists('woocommerce_wp_textarea_input')) {
			return;
		}

		woocommerce_wp_textarea_input(
			array(
				'id'            => $field_id,
				'label'         => $label,
				'value'         => $value,
				'description'   => $description,
				'desc_tip'      => false,
				'rows'          => max(3, $rows),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Рендерира HTML editor поле с wp_editor.
	 *
	 * @param string $field_id Field ID.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_html_editor_field(
		string $field_id,
		string $label,
		string $value,
		string $description
	): void {
		echo '<div class="options_group">';
		echo '<p class="form-field form-field-wide">';
		echo '<label for="' . esc_attr($field_id) . '"><strong>' . esc_html($label) . '</strong></label>';
		echo '<span class="description" style="display:block;margin:8px 0 10px 0;">' . esc_html($description) . '</span>';

		wp_editor(
			$value,
			$field_id,
			array(
				'textarea_name' => $field_id,
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
				'quicktags'     => true,
				'editor_class'  => 'wdt-wcpe-html-editor',
			)
		);

		echo '</p>';
		echo '</div>';
	}

	/**
	 * Показва helper блок за placeholders.
	 *
	 * @return void
	 */
	private static function render_placeholder_help_box(): void
	{
		$placeholders = array(
			'{customer_email}',
			'{customer_first_name}',
			'{customer_last_name}',
			'{customer_full_name}',
			'{order_id}',
			'{order_number}',
			'{order_status}',
			'{product_name}',
			'{product_sku}',
			'{product_id}',
			'{site_name}',
		);

		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<strong>' . esc_html__('Supported placeholders', 'webdigitech-woo-product-emailer') . '</strong><br />';
		echo '<span class="description">';
		echo esc_html__(
			'You can use the following placeholders inside subject, heading and body fields:',
			'webdigitech-woo-product-emailer'
		);
		echo '</span>';
		echo '</p>';

		echo '<p class="form-field">';
		foreach ($placeholders as $placeholder) {
			echo '<code style="margin-right:8px;display:inline-block;margin-bottom:6px;">' . esc_html($placeholder) . '</code>';
		}
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Показва live статус информация за шаблона.
	 *
	 * @param array<string, mixed> $values Полетата на продукта.
	 * @return void
	 */
	private static function render_template_status_box(array $values): void
	{
		$status_data = self::build_template_status_data($values);

		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<strong>' . esc_html__('Template status', 'webdigitech-woo-product-emailer') . '</strong><br />';
		echo '<span id="' . esc_attr(self::STATUS_TEXT_ID) . '" class="description" style="display:block;' . esc_attr(self::status_style($status_data['tone'])) . '">';
		echo esc_html($status_data['message']);
		echo '</span>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Рендерира inline script за live обновяване на template status.
	 *
	 * @return void
	 */
	private static function render_live_status_script(): void
	{
		$panel_id           = self::TAB_ID . '_panel';
		$status_text_id     = self::STATUS_TEXT_ID;
		$enable_custom_id   = Constants::META_ENABLE_CUSTOM_EMAIL;
		$subject_id         = Constants::META_PRODUCT_EMAIL_SUBJECT;
		$body_text_id       = Constants::META_PRODUCT_EMAIL_BODY_TEXT;
		$html_editor_id     = Constants::META_PRODUCT_EMAIL_BODY_HTML;
		$disabled_message   = __('Custom product email is disabled. The product will use the global fallback template if one is configured.', 'webdigitech-woo-product-emailer');
		$incomplete_message = __('Custom email is enabled, but the template is incomplete. Add at least a subject and either HTML body or text body.', 'webdigitech-woo-product-emailer');
		$complete_message   = __('Ready', 'webdigitech-woo-product-emailer');
		$disabled_style     = self::status_style('neutral');
		$warning_style      = self::status_style('warning');
		$success_style      = self::status_style('success');

		echo '<script type="text/javascript">';
		echo '(function(){';
		echo 'function boot(){';
		echo 'var panel=document.getElementById(' . wp_json_encode($panel_id) . ');';
		echo 'if(!panel){return;}';
		echo 'var statusText=document.getElementById(' . wp_json_encode($status_text_id) . ');';
		echo 'if(!statusText){return;}';
		echo 'var enableCheckbox=panel.querySelector("#" + ' . wp_json_encode($enable_custom_id) . ');';
		echo 'var subjectInput=panel.querySelector("#" + ' . wp_json_encode($subject_id) . ');';
		echo 'var bodyTextInput=panel.querySelector("#" + ' . wp_json_encode($body_text_id) . ');';

		echo 'function getEditorTextareaValue(){';
		echo 'var textarea=panel.querySelector("#" + ' . wp_json_encode($html_editor_id) . ');';
		echo 'return textarea ? String(textarea.value || "") : "";';
		echo '}';

		echo 'function getEditorContent(){';
		echo 'if(window.tinymce && typeof window.tinymce.get==="function"){';
		echo 'var editor=window.tinymce.get(' . wp_json_encode($html_editor_id) . ');';
		echo 'if(editor && !editor.isHidden()){';
		echo 'try{return String(editor.getContent({format:"raw"}) || "");}catch(error){}';
		echo '}';
		echo '}';
		echo 'return getEditorTextareaValue();';
		echo '}';

		echo 'function hasContent(value){';
		echo 'return String(value || "").trim() !== "";';
		echo '}';

		echo 'function setStatus(message, styleValue){';
		echo 'statusText.textContent=message;';
		echo 'statusText.setAttribute("style","display:block;" + styleValue);';
		echo '}';

		echo 'function refreshStatus(){';
		echo 'var isEnabled=!!(enableCheckbox && enableCheckbox.checked);';
		echo 'var subject=subjectInput ? String(subjectInput.value || "") : "";';
		echo 'var bodyText=bodyTextInput ? String(bodyTextInput.value || "") : "";';
		echo 'var bodyHtml=getEditorContent();';
		echo 'var isComplete=hasContent(subject) && (hasContent(bodyHtml) || hasContent(bodyText));';
		echo 'if(!isEnabled){setStatus(' . wp_json_encode($disabled_message) . ',' . wp_json_encode($disabled_style) . ');return;}';
		echo 'if(!isComplete){setStatus(' . wp_json_encode($incomplete_message) . ',' . wp_json_encode($warning_style) . ');return;}';
		echo 'setStatus(' . wp_json_encode($complete_message) . ',' . wp_json_encode($success_style) . ');';
		echo '}';

		echo 'if(enableCheckbox){';
		echo 'enableCheckbox.addEventListener("change",refreshStatus);';
		echo '}';

		echo 'if(subjectInput){';
		echo 'subjectInput.addEventListener("input",refreshStatus);';
		echo 'subjectInput.addEventListener("change",refreshStatus);';
		echo '}';

		echo 'if(bodyTextInput){';
		echo 'bodyTextInput.addEventListener("input",refreshStatus);';
		echo 'bodyTextInput.addEventListener("change",refreshStatus);';
		echo '}';

		echo 'var htmlTextarea=panel.querySelector("#" + ' . wp_json_encode($html_editor_id) . ');';
		echo 'if(htmlTextarea){';
		echo 'htmlTextarea.addEventListener("input",refreshStatus);';
		echo 'htmlTextarea.addEventListener("change",refreshStatus);';
		echo '}';

		echo 'if(window.tinymce){';
		echo 'var attachTinyMceListeners=function(){';
		echo 'var editor=window.tinymce.get(' . wp_json_encode($html_editor_id) . ');';
		echo 'if(!editor || editor._wdtWcpeStatusBound){return;}';
		echo 'editor._wdtWcpeStatusBound=true;';
		echo 'editor.on("input change keyup SetContent Undo Redo",refreshStatus);';
		echo '};';
		echo 'attachTinyMceListeners();';
		echo 'if(typeof window.tinymce.on==="function"){';
		echo 'window.tinymce.on("AddEditor",function(event){';
		echo 'if(event && event.editor && event.editor.id===' . wp_json_encode($html_editor_id) . '){';
		echo 'window.setTimeout(attachTinyMceListeners,0);';
		echo '}';
		echo '});';
		echo '}';
		echo '}';

		echo 'window.setTimeout(refreshStatus,0);';
		echo 'document.addEventListener("woocommerce_variations_loaded",refreshStatus);';
		echo '}';

		echo 'if(document.readyState==="loading"){';
		echo 'document.addEventListener("DOMContentLoaded",boot);';
		echo '}else{';
		echo 'boot();';
		echo '}';
		echo '})();';
		echo '</script>';
	}

	/**
	 * Изчислява текущия статус на шаблона.
	 *
	 * @param array<string, mixed> $values Полетата на продукта.
	 * @return array{message:string,tone:string}
	 */
	private static function build_template_status_data(array $values): array
	{
		$is_custom_enabled = Helpers::sanitize_yes_no(
			Helpers::array_get($values, Constants::META_ENABLE_CUSTOM_EMAIL, 'no'),
			'no'
		) === 'yes';

		$subject = Helpers::sanitize_email_subject(
			Helpers::array_get($values, Constants::META_PRODUCT_EMAIL_SUBJECT, '')
		);

		$body_html = Helpers::sanitize_template_html(
			Helpers::array_get($values, Constants::META_PRODUCT_EMAIL_BODY_HTML, '')
		);

		$body_text = Helpers::sanitize_template_text(
			Helpers::array_get($values, Constants::META_PRODUCT_EMAIL_BODY_TEXT, '')
		);

		$is_template_complete = $subject !== '' && ($body_html !== '' || $body_text !== '');

		if (! $is_custom_enabled) {
			return array(
				'message' => __('Custom product email is disabled. The product will use the global fallback template if one is configured.', 'webdigitech-woo-product-emailer'),
				'tone'    => 'neutral',
			);
		}

		if (! $is_template_complete) {
			return array(
				'message' => __('Custom email is enabled, but the template is incomplete. Add at least a subject and either HTML body or text body.', 'webdigitech-woo-product-emailer'),
				'tone'    => 'warning',
			);
		}

		return array(
			'message' => __('Ready', 'webdigitech-woo-product-emailer'),
			'tone'    => 'success',
		);
	}

	/**
	 * Връща inline style за status tone.
	 *
	 * @param string $tone Тон на статуса.
	 * @return string
	 */
	private static function status_style(string $tone): string
	{
		switch ($tone) {
			case 'success':
				return 'color:#15803d;font-weight:600;';
			case 'warning':
				return 'color:#b45309;font-weight:600;';
			case 'neutral':
			default:
				return 'color:#50575e;';
		}
	}

	/**
	 * Safe debug лог.
	 *
	 * @param string               $message Съобщение.
	 * @param array<string, mixed> $context Контекст.
	 * @return void
	 */
	private static function log_debug(string $message, array $context = array()): void
	{
		if (class_exists('\\WebDigiTech\\WooProductEmailer\\Logger')) {
			Logger::log_debug($message, $context);
		}
	}

	/**
	 * Забранява инстанциране.
	 */
	private function __construct()
	{
	}
}