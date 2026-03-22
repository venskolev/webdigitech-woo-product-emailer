(function ($) {
	"use strict";

	/**
	 * Взема глобалната конфигурация, подадена от wp_localize_script.
	 *
	 * @returns {object}
	 */
	function getConfig() {
		if (typeof window.wdtWcpeAdmin !== "object" || window.wdtWcpeAdmin === null) {
			return {};
		}

		return window.wdtWcpeAdmin;
	}

	/**
	 * Escape на текст за HTML контекст.
	 *
	 * @param {string} value Стойност.
	 * @returns {string}
	 */
	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}

	/**
	 * Създава или намира контейнер за runtime съобщения.
	 *
	 * @returns {jQuery|null}
	 */
	function getRuntimeNoticeContainer() {
		var $container = $("#wdt-wcpe-runtime-notice");

		if ($container.length > 0) {
			return $container;
		}

		var $toolsCard = $("#wdt-wcpe-test-email-card");

		if ($toolsCard.length === 0) {
			return null;
		}

		$container = $('<div id="wdt-wcpe-runtime-notice" style="margin:12px 0 16px;"></div>');
		$toolsCard.find("h2").first().after($container);

		return $container;
	}

	/**
	 * Премахва runtime notice.
	 *
	 * @returns {void}
	 */
	function clearRuntimeNotice() {
		var $container = getRuntimeNoticeContainer();

		if (!$container) {
			return;
		}

		$container.empty();
	}

	/**
	 * Показва runtime notice.
	 *
	 * @param {"success"|"error"|"warning"|"info"} type Тип notice.
	 * @param {string} message Съобщение.
	 * @returns {void}
	 */
	function showRuntimeNotice(type, message) {
		var $container = getRuntimeNoticeContainer();

		if (!$container) {
			return;
		}

		var noticeClass = "notice notice-info inline";

		if (type === "success") {
			noticeClass = "notice notice-success inline";
		} else if (type === "error") {
			noticeClass = "notice notice-error inline";
		} else if (type === "warning") {
			noticeClass = "notice notice-warning inline";
		}

		$container.html(
			'<div class="' + noticeClass + '"><p>' + escapeHtml(message) + "</p></div>"
		);
	}

	/**
	 * Проверява дали сме на Tools страницата на плъгина.
	 *
	 * @returns {boolean}
	 */
	function isToolsPage() {
		return $("#wdt-wcpe-test-email-card").length > 0;
	}

	/**
	 * Задава стойност в input/textarea, ако е налично.
	 *
	 * @param {string} selector CSS селектор.
	 * @param {string} value Стойност.
	 * @returns {void}
	 */
	function setFieldValue(selector, value) {
		var $field = $(selector);

		if ($field.length === 0) {
			return;
		}

		$field.val(value);
	}

	/**
	 * Обновява UI след успешно изпращане на тестов email.
	 *
	 * @param {object} responseData Payload от AJAX success.
	 * @returns {void}
	 */
	function updateTestEmailRuntimeFields(responseData) {
		if (!responseData || typeof responseData !== "object") {
			return;
		}

		if (typeof responseData.sent_at === "string" && responseData.sent_at !== "") {
			setFieldValue("#wdt-wcpe-last-test-email-sent-at", responseData.sent_at);
		}

		if (typeof responseData.recipient === "string" && responseData.recipient !== "") {
			setFieldValue("#wdt-wcpe-test-email-recipient", responseData.recipient);
		}

		if (
			typeof responseData.subject_preview === "string" &&
			responseData.subject_preview !== ""
		) {
			setFieldValue("#wdt-wcpe-test-email-subject", responseData.subject_preview);
		}

		setFieldValue("#wdt-wcpe-last-test-email-error", "");
	}

	/**
	 * Обновява UI след неуспешен тестов email.
	 *
	 * @param {string} message Съобщение за грешка.
	 * @returns {void}
	 */
	function updateTestEmailErrorField(message) {
		setFieldValue("#wdt-wcpe-last-test-email-error", message || "");
	}

	/**
	 * Връща payload за test email AJAX заявката.
	 *
	 * @param {object} config Конфигурация.
	 * @returns {object}
	 */
	function buildTestEmailPayload(config) {
		return {
			action: config.i18n && config.i18n.testEmailAction ? config.i18n.testEmailAction : "",
			nonce: config.nonce || "",
			recipient: $("#wdt-wcpe-test-email-recipient").val() || "",
			subject: $("#wdt-wcpe-test-email-subject").val() || "",
			heading: $("#wdt-wcpe-test-email-heading").val() || "",
			text_body: $("#wdt-wcpe-test-email-body").val() || "",
		};
	}

	/**
	 * Извлича server message от успешен/неуспешен AJAX отговор.
	 *
	 * @param {object} response AJAX response.
	 * @returns {string}
	 */
	function extractResponseMessage(response) {
		if (
			response &&
			response.data &&
			typeof response.data.message === "string" &&
			response.data.message !== ""
		) {
			return response.data.message;
		}

		return "";
	}

	/**
	 * Активира AJAX изпращането на тестов email.
	 *
	 * @returns {void}
	 */
	function bindTestEmailAction() {
		var $button = $("#wdt-wcpe-send-test-email");

		if ($button.length === 0) {
			return;
		}

		$button.on("click", function () {
			var config = getConfig();

			if (!config.ajaxUrl || !config.nonce) {
				showRuntimeNotice(
					"error",
					(config.i18n && config.i18n.genericError) || "Missing AJAX configuration."
				);
				return;
			}

			var recipient = String($("#wdt-wcpe-test-email-recipient").val() || "").trim();

			if (recipient === "") {
				showRuntimeNotice("warning", "Please enter a recipient email address first.");
				$("#wdt-wcpe-test-email-recipient").trigger("focus");
				return;
			}

			var payload = buildTestEmailPayload(config);
			var originalLabel = $button.text();

			$button.prop("disabled", true).text("Sending...");
			clearRuntimeNotice();
			showRuntimeNotice("info", "Sending test email...");

			$.ajax({
				url: config.ajaxUrl,
				type: "POST",
				dataType: "json",
				data: payload,
			})
				.done(function (response) {
					var fallbackMessage =
						(config.i18n && config.i18n.genericError) ||
						"Something went wrong. Please try again.";

					if (response && response.success && response.data) {
						updateTestEmailRuntimeFields(response.data);
						showRuntimeNotice(
							"success",
							extractResponseMessage(response) || "Test email sent successfully."
						);
						return;
					}

					var message = extractResponseMessage(response) || fallbackMessage;

					updateTestEmailErrorField(message);
					showRuntimeNotice("error", message);
				})
				.fail(function (xhr) {
					var fallbackMessage =
						(config.i18n && config.i18n.genericError) ||
						"Something went wrong. Please try again.";

					var serverMessage = "";

					if (
						xhr &&
						xhr.responseJSON &&
						xhr.responseJSON.data &&
						typeof xhr.responseJSON.data.message === "string"
					) {
						serverMessage = xhr.responseJSON.data.message;
					}

					var finalMessage = serverMessage || fallbackMessage;

					updateTestEmailErrorField(finalMessage);
					showRuntimeNotice("error", finalMessage);
				})
				.always(function () {
					$button.prop("disabled", false).text(originalLabel);
				});
		});
	}

	/**
	 * Инициализира admin JS логиката.
	 *
	 * @returns {void}
	 */
	function init() {
		if (!isToolsPage()) {
			return;
		}

		bindTestEmailAction();
	}

	$(document).ready(init);
})(jQuery);