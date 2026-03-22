(function ($) {
	"use strict";

	/**
	 * Главен wrapper на product email panel-а.
	 *
	 * Поддържаме няколко възможни selector-а, за да не сме крехки
	 * спрямо текущия markup от ProductEmailFields.
	 *
	 * @returns {jQuery}
	 */
	function getPanel() {
		var selectors = [
			"#wdt-wcpe-product-email-panel",
			".wdt-wcpe-product-panel",
			"#wdt-wcpe-product-email-fields",
			".wdt-wcpe-product-email-fields",
		];

		for (var i = 0; i < selectors.length; i += 1) {
			var $panel = $(selectors[i]).first();

			if ($panel.length > 0) {
				return $panel;
			}
		}

		return $();
	}

	/**
	 * Escape на текст за HTML.
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
	 * Нормализира label текст.
	 *
	 * @param {string} text Текст.
	 * @returns {string}
	 */
	function normalizeLabel(text) {
		return String(text || "")
			.replace(/\s+/g, " ")
			.replace(/\*+/g, "")
			.trim();
	}

	/**
	 * Връща първия field по име/id/част от име.
	 *
	 * @param {jQuery} $scope Scope.
	 * @param {string[]} candidates Кандидати.
	 * @returns {jQuery}
	 */
	function findFieldByCandidates($scope, candidates) {
		for (var i = 0; i < candidates.length; i += 1) {
			var candidate = candidates[i];

			var $byId = $scope.find("#" + candidate).first();
			if ($byId.length > 0) {
				return $byId;
			}

			var $byNameExact = $scope.find('[name="' + candidate + '"]').first();
			if ($byNameExact.length > 0) {
				return $byNameExact;
			}

			var $byNameContains = $scope.find('[name*="' + candidate + '"]').first();
			if ($byNameContains.length > 0) {
				return $byNameContains;
			}
		}

		return $();
	}

	/**
	 * Връща label текст за field.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {string}
	 */
	function getFieldLabelText($field) {
		if (!$field || $field.length === 0) {
			return "";
		}

		var fieldId = String($field.attr("id") || "").trim();

		if (fieldId !== "") {
			var $labelFor = $('label[for="' + fieldId + '"]').first();
			if ($labelFor.length > 0) {
				return normalizeLabel($labelFor.text());
			}
		}

		var $closestP = $field.closest("p.form-field");
		if ($closestP.length > 0) {
			var $labelInsideP = $closestP.children("label").first();
			if ($labelInsideP.length > 0) {
				return normalizeLabel($labelInsideP.text());
			}
		}

		var $closestTd = $field.closest("td");
		if ($closestTd.length > 0) {
			var $th = $closestTd.prev("th");
			if ($th.length > 0) {
				return normalizeLabel($th.text());
			}
		}

		var $closestTr = $field.closest("tr");
		if ($closestTr.length > 0) {
			var $thInRow = $closestTr.children("th").first();
			if ($thInRow.length > 0) {
				return normalizeLabel($thInRow.text());
			}
		}

		return "";
	}

	/**
	 * Връща hint/description за field.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {string}
	 */
	function getFieldDescriptionText($field) {
		if (!$field || $field.length === 0) {
			return "";
		}

		var $container = $field.closest("p.form-field");
		if ($container.length > 0) {
			var $desc = $container.find(".description").first();
			if ($desc.length > 0) {
				return $.trim($desc.text());
			}
		}

		var $td = $field.closest("td");
		if ($td.length > 0) {
			var $descInTd = $td.find(".description").first();
			if ($descInTd.length > 0) {
				return $.trim($descInTd.text());
			}
		}

		return "";
	}

	/**
	 * Преценява дали полето е textarea body.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {boolean}
	 */
	function isBodyField($field) {
		var name = String($field.attr("name") || "").toLowerCase();
		var id = String($field.attr("id") || "").toLowerCase();
		var label = getFieldLabelText($field).toLowerCase();

		return (
			name.indexOf("body") !== -1 ||
			id.indexOf("body") !== -1 ||
			label.indexOf("body") !== -1 ||
			label.indexOf("html") !== -1 ||
			label.indexOf("text") !== -1 ||
			label.indexOf("message") !== -1
		);
	}

	/**
	 * Преценява дали полето е subject.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {boolean}
	 */
	function isSubjectField($field) {
		var name = String($field.attr("name") || "").toLowerCase();
		var id = String($field.attr("id") || "").toLowerCase();
		var label = getFieldLabelText($field).toLowerCase();

		return (
			name.indexOf("subject") !== -1 ||
			id.indexOf("subject") !== -1 ||
			label.indexOf("subject") !== -1
		);
	}

	/**
	 * Преценява дали полето е heading.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {boolean}
	 */
	function isHeadingField($field) {
		var name = String($field.attr("name") || "").toLowerCase();
		var id = String($field.attr("id") || "").toLowerCase();
		var label = getFieldLabelText($field).toLowerCase();

		return (
			name.indexOf("heading") !== -1 ||
			id.indexOf("heading") !== -1 ||
			label.indexOf("heading") !== -1
		);
	}

	/**
	 * Преценява дали полето е enable/use toggle.
	 *
	 * @param {jQuery} $field Поле.
	 * @returns {boolean}
	 */
	function isEnableField($field) {
		var name = String($field.attr("name") || "").toLowerCase();
		var id = String($field.attr("id") || "").toLowerCase();
		var label = getFieldLabelText($field).toLowerCase();

		return (
			name.indexOf("enable") !== -1 ||
			name.indexOf("enabled") !== -1 ||
			name.indexOf("active") !== -1 ||
			name.indexOf("use_") !== -1 ||
			id.indexOf("enable") !== -1 ||
			id.indexOf("enabled") !== -1 ||
			id.indexOf("active") !== -1 ||
			label.indexOf("enable") !== -1 ||
			label.indexOf("active") !== -1 ||
			label.indexOf("use") !== -1
		);
	}

	/**
	 * Опитва да открие основните полета.
	 *
	 * @param {jQuery} $panel Panel.
	 * @returns {object}
	 */
	function detectFields($panel) {
		var result = {
			enable: $(),
			subject: $(),
			heading: $(),
			bodyHtml: $(),
			bodyText: $(),
		};

		result.enable = findFieldByCandidates($panel, [
			"wdt_wcpe_enabled",
			"wdt_wcpe_enable_email",
			"wdt_wcpe_email_enabled",
			"enable_product_email",
			"product_email_enabled",
		]);

		result.subject = findFieldByCandidates($panel, [
			"wdt_wcpe_subject",
			"wdt_wcpe_email_subject",
			"product_email_subject",
			"email_subject",
		]);

		result.heading = findFieldByCandidates($panel, [
			"wdt_wcpe_heading",
			"wdt_wcpe_email_heading",
			"product_email_heading",
			"email_heading",
		]);

		result.bodyHtml = findFieldByCandidates($panel, [
			"wdt_wcpe_body_html",
			"wdt_wcpe_email_body_html",
			"product_email_body_html",
			"email_body_html",
		]);

		result.bodyText = findFieldByCandidates($panel, [
			"wdt_wcpe_body_text",
			"wdt_wcpe_email_body_text",
			"product_email_body_text",
			"email_body_text",
		]);

		if (result.subject.length === 0 || result.heading.length === 0 || (result.bodyHtml.length === 0 && result.bodyText.length === 0)) {
			var $allInputs = $panel.find("input[type='text'], textarea");

			$allInputs.each(function () {
				var $field = $(this);

				if (result.subject.length === 0 && isSubjectField($field)) {
					result.subject = $field;
					return;
				}

				if (result.heading.length === 0 && isHeadingField($field)) {
					result.heading = $field;
					return;
				}

				if ($field.is("textarea")) {
					var labelText = getFieldLabelText($field).toLowerCase();
					var name = String($field.attr("name") || "").toLowerCase();
					var id = String($field.attr("id") || "").toLowerCase();

					if (
						result.bodyHtml.length === 0 &&
						(labelText.indexOf("html") !== -1 || name.indexOf("html") !== -1 || id.indexOf("html") !== -1)
					) {
						result.bodyHtml = $field;
						return;
					}

					if (
						result.bodyText.length === 0 &&
						(labelText.indexOf("plain") !== -1 ||
							labelText.indexOf("text") !== -1 ||
							name.indexOf("text") !== -1 ||
							id.indexOf("text") !== -1)
					) {
						result.bodyText = $field;
						return;
					}
				}
			});
		}

		if (result.enable.length === 0) {
			$panel.find("input[type='checkbox']").each(function () {
				var $field = $(this);
				if (isEnableField($field)) {
					result.enable = $field;
					return false;
				}
				return true;
			});
		}

		if (result.bodyHtml.length === 0 || result.bodyText.length === 0) {
			var $textareas = $panel.find("textarea");

			$textareas.each(function () {
				var $field = $(this);

				if (!isBodyField($field)) {
					return;
				}

				var labelText = getFieldLabelText($field).toLowerCase();

				if (
					result.bodyHtml.length === 0 &&
					(labelText.indexOf("html") !== -1)
				) {
					result.bodyHtml = $field;
					return;
				}

				if (
					result.bodyText.length === 0 &&
					(labelText.indexOf("plain") !== -1 || labelText.indexOf("text") !== -1)
				) {
					result.bodyText = $field;
					return;
				}

				if (result.bodyHtml.length === 0) {
					result.bodyHtml = $field;
					return;
				}

				if (result.bodyText.length === 0 && !$field.is(result.bodyHtml)) {
					result.bodyText = $field;
				}
			});
		}

		return result;
	}

	/**
	 * Създава layout класове върху panel-а.
	 *
	 * @param {jQuery} $panel Panel.
	 * @returns {void}
	 */
	function hydratePanelShell($panel) {
		if ($panel.length === 0) {
			return;
		}

		$panel.addClass("wdt-wcpe-product-panel");

		var $firstHeading = $panel.find("h2, h3").first();
		if ($firstHeading.length > 0 && !$firstHeading.parent().hasClass("wdt-wcpe-product-panel__header")) {
			var subtitle = "";
			var $nextParagraph = $firstHeading.next("p");

			if ($nextParagraph.length > 0) {
				subtitle = $.trim($nextParagraph.text());
				$nextParagraph.remove();
			}

			var titleText = $.trim($firstHeading.text());
			$firstHeading.remove();

			var headerHtml =
				'<div class="wdt-wcpe-product-panel__header">' +
				'<h3 class="wdt-wcpe-product-panel__title">' + escapeHtml(titleText || "Product Email") + "</h3>";

			if (subtitle !== "") {
				headerHtml += '<p class="wdt-wcpe-product-panel__subtitle">' + escapeHtml(subtitle) + "</p>";
			}

			headerHtml += "</div>";

			$panel.prepend(headerHtml);
		}

		if ($panel.children(".wdt-wcpe-product-panel__body").length === 0) {
			$panel.wrapInner('<div class="wdt-wcpe-product-panel__body"></div>');

			var $body = $panel.children(".wdt-wcpe-product-panel__body").first();
			var $header = $body.children(".wdt-wcpe-product-panel__header").first();

			if ($header.length > 0) {
				$header.prependTo($panel);
			}
		}
	}

	/**
	 * Премества поле в собствен wrapper ако е нужно.
	 *
	 * @param {jQuery} $field Поле.
	 * @param {string} customClass Допълнителен клас.
	 * @returns {jQuery}
	 */
	function ensureFieldWrapper($field, customClass) {
		if (!$field || $field.length === 0) {
			return $();
		}

		var $current = $field.closest(".wdt-wcpe-product-panel__field");
		if ($current.length > 0) {
			if (customClass) {
				$current.addClass(customClass);
			}
			return $current;
		}

		var labelText = getFieldLabelText($field);
		var descriptionText = getFieldDescriptionText($field);
		var $originalContainer = $field.closest("p.form-field");

		if ($originalContainer.length === 0) {
			var $td = $field.closest("td");
			if ($td.length > 0) {
				$originalContainer = $('<div class="wdt-wcpe-product-panel__field"></div>');
				if (customClass) {
					$originalContainer.addClass(customClass);
				}

				if (labelText !== "") {
					$originalContainer.append(
						'<label class="wdt-wcpe-product-panel__label">' + escapeHtml(labelText) + "</label>"
					);
				}

				$field.before($originalContainer);
				$originalContainer.append($field);

				if (descriptionText !== "") {
					$originalContainer.append(
						'<p class="wdt-wcpe-product-panel__hint">' + escapeHtml(descriptionText) + "</p>"
					);
				}

				$td.closest("tr").hide();
				return $originalContainer;
			}

			$originalContainer = $field.parent();
		}

		var $wrapper = $('<div class="wdt-wcpe-product-panel__field"></div>');
		if (customClass) {
			$wrapper.addClass(customClass);
		}

		if (labelText !== "") {
			$wrapper.append(
				'<label class="wdt-wcpe-product-panel__label">' + escapeHtml(labelText) + "</label>"
			);
		}

		$field.detach();
		$wrapper.append($field);

		if (descriptionText !== "") {
			$wrapper.append(
				'<p class="wdt-wcpe-product-panel__hint">' + escapeHtml(descriptionText) + "</p>"
			);
		}

		$originalContainer.after($wrapper).hide();

		return $wrapper;
	}

	/**
	 * Подготвя layout grid и мести откритите полета.
	 *
	 * @param {jQuery} $panel Panel.
	 * @param {object} fields Полета.
	 * @returns {void}
	 */
	function buildStructuredLayout($panel, fields) {
		var $body = $panel.children(".wdt-wcpe-product-panel__body").first();
		if ($body.length === 0) {
			return;
		}

		if ($body.children(".wdt-wcpe-product-panel__section").length > 0) {
			return;
		}

		var generalHtml =
			'<div class="wdt-wcpe-product-panel__section wdt-wcpe-product-panel__section--general">' +
			'<h4 class="wdt-wcpe-product-panel__section-title">General settings</h4>' +
			'<p class="wdt-wcpe-product-panel__section-description">Configure whether this product should send its own email and define the core subject / heading content.</p>' +
			'<div class="wdt-wcpe-product-panel__grid wdt-wcpe-product-panel__grid--general"></div>' +
			"</div>";

		var contentHtml =
			'<div class="wdt-wcpe-product-panel__section wdt-wcpe-product-panel__section--content">' +
			'<h4 class="wdt-wcpe-product-panel__section-title">Email content</h4>' +
			'<p class="wdt-wcpe-product-panel__section-description">Write the product-specific email body. Placeholders can be used for customer, order and product values.</p>' +
			'<div class="wdt-wcpe-product-panel__grid wdt-wcpe-product-panel__grid--content"></div>' +
			"</div>";

		var previewHtml =
			'<div class="wdt-wcpe-product-panel__section wdt-wcpe-product-panel__section--preview">' +
			'<h4 class="wdt-wcpe-product-panel__section-title">Live preview</h4>' +
			'<p class="wdt-wcpe-product-panel__section-description">This preview is only a helper for writing. Real placeholders are resolved later during email sending.</p>' +
			'<div class="wdt-wcpe-product-panel__preview">' +
			'<p class="wdt-wcpe-product-panel__preview-title">Email preview</p>' +
			'<div class="wdt-wcpe-product-panel__preview-content" id="wdt-wcpe-product-email-preview">No content yet.</div>' +
			"</div>" +
			'<div class="wdt-wcpe-product-panel__tokens" id="wdt-wcpe-product-email-tokens"></div>' +
			"</div>";

		$body.append(generalHtml);
		$body.append(contentHtml);
		$body.append(previewHtml);

		var $generalGrid = $body.find(".wdt-wcpe-product-panel__grid--general").first();
		var $contentGrid = $body.find(".wdt-wcpe-product-panel__grid--content").first();

		if (fields.enable.length > 0) {
			var $enableWrapper = ensureCheckboxWrapper(fields.enable);
			if ($enableWrapper.length > 0) {
				$generalGrid.append($enableWrapper);
			}
		}

		if (fields.subject.length > 0) {
			$generalGrid.append(ensureFieldWrapper(fields.subject, ""));
		}

		if (fields.heading.length > 0) {
			$generalGrid.append(ensureFieldWrapper(fields.heading, ""));
		}

		if (fields.bodyHtml.length > 0) {
			$contentGrid.append(ensureFieldWrapper(fields.bodyHtml, "wdt-wcpe-product-panel__field--full"));
		}

		if (fields.bodyText.length > 0) {
			$contentGrid.append(ensureFieldWrapper(fields.bodyText, "wdt-wcpe-product-panel__field--full"));
		}
	}

	/**
	 * Специален wrapper за checkbox поле.
	 *
	 * @param {jQuery} $field Checkbox поле.
	 * @returns {jQuery}
	 */
	function ensureCheckboxWrapper($field) {
		if (!$field || $field.length === 0) {
			return $();
		}

		var $existing = $field.closest(".wdt-wcpe-product-panel__checkbox");
		if ($existing.length > 0) {
			return $existing.closest(".wdt-wcpe-product-panel__field").length > 0
				? $existing.closest(".wdt-wcpe-product-panel__field")
				: $existing;
		}

		var labelText = getFieldLabelText($field) || "Enable product email";
		var descriptionText = getFieldDescriptionText($field);
		var $originalContainer = $field.closest("p.form-field");

		var $fieldWrapper = $('<div class="wdt-wcpe-product-panel__field wdt-wcpe-product-panel__field--full"></div>');
		var $checkboxBox = $('<label class="wdt-wcpe-product-panel__checkbox"></label>');
		var $text = $('<span class="wdt-wcpe-product-panel__checkbox-text"></span>');

		$field.detach();
		$checkboxBox.append($field);

		$text.append('<span class="wdt-wcpe-product-panel__checkbox-title">' + escapeHtml(labelText) + "</span>");

		if (descriptionText !== "") {
			$text.append('<span class="wdt-wcpe-product-panel__checkbox-description">' + escapeHtml(descriptionText) + "</span>");
		}

		$checkboxBox.append($text);
		$fieldWrapper.append($checkboxBox);

		if ($originalContainer.length > 0) {
			$originalContainer.after($fieldWrapper).hide();
		} else {
			$field.parent().append($fieldWrapper);
		}

		return $fieldWrapper;
	}

	/**
	 * Извлича плейсхолдъри от текст.
	 *
	 * @param {string} value Текст.
	 * @returns {string[]}
	 */
	function extractPlaceholders(value) {
		var matches = String(value || "").match(/\{[a-z0-9_\-]+\}/gi);
		if (!matches) {
			return [];
		}

		var unique = [];
		for (var i = 0; i < matches.length; i += 1) {
			if (unique.indexOf(matches[i]) === -1) {
				unique.push(matches[i]);
			}
		}

		return unique;
	}

	/**
	 * Обновява preview съдържанието.
	 *
	 * @param {object} fields Полета.
	 * @returns {void}
	 */
	function updatePreview(fields) {
		var $preview = $("#wdt-wcpe-product-email-preview");
		var $tokens = $("#wdt-wcpe-product-email-tokens");

		if ($preview.length === 0 || $tokens.length === 0) {
			return;
		}

		var subject = fields.subject.length > 0 ? String(fields.subject.val() || "").trim() : "";
		var heading = fields.heading.length > 0 ? String(fields.heading.val() || "").trim() : "";
		var bodyHtml = fields.bodyHtml.length > 0 ? String(fields.bodyHtml.val() || "").trim() : "";
		var bodyText = fields.bodyText.length > 0 ? String(fields.bodyText.val() || "").trim() : "";

		var previewParts = [];

		if (subject !== "") {
			previewParts.push("Subject: " + subject);
		}

		if (heading !== "") {
			previewParts.push("Heading: " + heading);
		}

		if (bodyHtml !== "") {
			previewParts.push("HTML Body:\n" + bodyHtml);
		}

		if (bodyText !== "") {
			previewParts.push("Text Body:\n" + bodyText);
		}

		if (previewParts.length === 0) {
			$preview.text("No content yet.");
		} else {
			$preview.text(previewParts.join("\n\n"));
		}

		var allText = [subject, heading, bodyHtml, bodyText].join("\n");
		var placeholders = extractPlaceholders(allText);

		$tokens.empty();

		if (placeholders.length === 0) {
			$tokens.append('<span class="wdt-wcpe-product-panel__token">No placeholders detected</span>');
			return;
		}

		for (var i = 0; i < placeholders.length; i += 1) {
			$tokens.append(
				'<span class="wdt-wcpe-product-panel__token">' + escapeHtml(placeholders[i]) + "</span>"
			);
		}
	}

	/**
	 * Връзва live preview обновяването.
	 *
	 * @param {object} fields Полета.
	 * @returns {void}
	 */
	function bindPreview(fields) {
		var bindTargets = [
			fields.subject,
			fields.heading,
			fields.bodyHtml,
			fields.bodyText,
		];

		for (var i = 0; i < bindTargets.length; i += 1) {
			var $target = bindTargets[i];
			if ($target && $target.length > 0) {
				$target.on("input change", function () {
					updatePreview(fields);
				});
			}
		}

		updatePreview(fields);
	}

	/**
	 * Управлява enable/disable състоянието на останалите полета.
	 *
	 * @param {object} fields Полета.
	 * @returns {void}
	 */
	function bindEnableToggle(fields) {
		if (!fields.enable || fields.enable.length === 0) {
			return;
		}

		var managedFields = [
			fields.subject,
			fields.heading,
			fields.bodyHtml,
			fields.bodyText,
		];

		function applyState() {
			var isEnabled = fields.enable.is(":checked");

			for (var i = 0; i < managedFields.length; i += 1) {
				var $field = managedFields[i];
				if (!$field || $field.length === 0) {
					continue;
				}

				$field.prop("disabled", !isEnabled);

				var $wrapper = $field.closest(".wdt-wcpe-product-panel__field");
				if ($wrapper.length > 0) {
					$wrapper.css("opacity", isEnabled ? "1" : "0.55");
				}
			}
		}

		fields.enable.on("change", applyState);
		applyState();
	}

	/**
	 * Добавя helper notice.
	 *
	 * @param {jQuery} $panel Panel.
	 * @returns {void}
	 */
	function injectTopNotice($panel) {
		var $body = $panel.children(".wdt-wcpe-product-panel__body").first();
		if ($body.length === 0) {
			return;
		}

		if ($body.children(".wdt-wcpe-product-panel__notice").length > 0) {
			return;
		}

		var html =
			'<div class="wdt-wcpe-product-panel__notice">' +
			"Configure a product-specific email template here. If disabled or incomplete, the plugin may fall back to the global template depending on your settings." +
			"</div>";

		$body.prepend(html);
	}

	/**
	 * Инициализира product panel логиката.
	 *
	 * @returns {void}
	 */
	function init() {
		var $panel = getPanel();

		if ($panel.length === 0) {
			return;
		}

		hydratePanelShell($panel);
		injectTopNotice($panel);

		var fields = detectFields($panel);
		buildStructuredLayout($panel, fields);
		bindEnableToggle(fields);
		bindPreview(fields);
	}

	$(document).ready(init);
})(jQuery);