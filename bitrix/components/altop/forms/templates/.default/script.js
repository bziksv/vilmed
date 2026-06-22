(function() {
	if (window.BX && window.BX.PopupFormSubmit) {
		return;
	}

	function clearFormErrors(form) {
		var rows = form.querySelectorAll(".row.has-error, .hint_agreement.has-error");
		for (var i = 0; i < rows.length; i++) {
			rows[i].classList.remove("has-error");
		}
	}

	function getFieldLabel(row) {
		var label = row.querySelector(".span1");
		if (!label) {
			return "Поле";
		}
		return label.textContent.replace(/\*/g, "").trim() || "Поле";
	}

	function validateForm(form) {
		var errors = [];
		var rows = form.querySelectorAll(".row");

		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			if (!row.querySelector(".mf-req")) {
				continue;
			}

			var input = row.querySelector(".span2 input[type='text'], .span2 textarea");
			if (!input) {
				continue;
			}

			if (!String(input.value || "").trim()) {
				row.classList.add("has-error");
				errors.push("Заполните поле «" + getFieldLabel(row) + "»");
			}
		}

		var personalData = form.querySelector("input[name='PERSONAL_DATA']");
		var agreement = form.querySelector(".hint_agreement");
		if (personalData && personalData.value !== "Y") {
			if (agreement) {
				agreement.classList.add("has-error");
			}
			errors.push("Подтвердите согласие на обработку персональных данных");
		}

		return errors;
	}

	function showAlert(alertNode, type, html) {
		if (!alertNode) {
			return;
		}

		var icon = type === "good" ? "fa-check-circle" : (type === "info" ? "fa-info-circle" : "fa-exclamation-circle");
		BX.adjust(alertNode, {
			html: "<span class='alertMsg " + type + " vilmed-form-alert' role='alert'><i class='fa " + icon + "'></i><span class='text'>" + html + "</span></span>"
		});

		if (alertNode.scrollIntoView) {
			alertNode.scrollIntoView({behavior: "smooth", block: "nearest"});
		}
	}

	function parseJsonResponse(response) {
		if (typeof response === "object" && response !== null) {
			return response;
		}

		if (typeof response !== "string" || !response.trim()) {
			return null;
		}

		try {
			return JSON.parse(response);
		} catch (e) {
			return null;
		}
	}

	window.BX = window.BX || {};
	window.BX.PopupFormSubmit = function() {
		var target = BX.proxy_context,
			popup = BX.findParent(target, {"className": "pop-up"}),
			form = BX.findParent(target, {"tag": "form"}),
			alert = BX.findChild(form, {"className": "alert"}, true, false),
			captchaWord = BX.findChild(form, {"attribute": {"name": "CAPTCHA_WORD"}}, true, false),
			captchaImg = BX.findChild(form, {"tagName": "img"}, true, false),
			captchaSid = BX.findChild(form, {"attribute": {"name": "CAPTCHA_SID"}}, true, false),
			formInput,
			formTextarea,
			data = {},
			errors,
			wait;

		clearFormErrors(form);
		if (alert) {
			BX.adjust(alert, {html: ""});
		}

		errors = validateForm(form);
		if (errors.length) {
			showAlert(alert, "bad", errors.join("<br>"));
			return;
		}

		formInput = BX.findChildren(form, {"tag": "input"}, true);
		if (formInput && formInput.length) {
			for (var i = 0; i < formInput.length; i++) {
				data[formInput[i].getAttribute("name")] = formInput[i].value;
			}
		}

		formTextarea = BX.findChildren(form, {"tag": "textarea"}, true);
		if (formTextarea && formTextarea.length) {
			for (var j = 0; j < formTextarea.length; j++) {
				data[formTextarea[j].getAttribute("name")] = formTextarea[j].value;
			}
		}

		wait = BX.showWait(popup);
		data.sessid = BX.bitrix_sessid();

		BX.ajax({
			url: form.getAttribute("action"),
			data: data,
			method: "POST",
			dataType: "json",
			onsuccess: function(response) {
				var parsed = parseJsonResponse(response);

				if (!parsed) {
					showAlert(alert, "bad", "Не удалось отправить форму. Попробуйте ещё раз.");
					BX.closeWait(popup, wait);
					return;
				}

				if (parsed.success) {
					showAlert(alert, "good", parsed.success.text);
					if (typeof ym === "function") {
						ym(55225453, "reachGoal", "ZaprositCenuAnalog2109231355");
					}
					BX.adjust(target, {props: {disabled: true}});
				} else if (parsed.error) {
					showAlert(alert, "bad", parsed.error.text);
					if (parsed.error.captcha_code) {
						if (captchaWord) {
							captchaWord.value = "";
						}
						if (captchaImg) {
							BX.adjust(captchaImg, {props: {"src": "/bitrix/tools/captcha.php?captcha_sid=" + parsed.error.captcha_code}});
						}
						if (captchaSid) {
							captchaSid.value = parsed.error.captcha_code;
						}
					}
				}

				BX.closeWait(popup, wait);
			},
			onfailure: function() {
				showAlert(alert, "bad", "Ошибка соединения. Проверьте интернет и попробуйте снова.");
				BX.closeWait(popup, wait);
			}
		});
	};
})();
