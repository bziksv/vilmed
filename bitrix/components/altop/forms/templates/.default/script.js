(function() {
	if (window.BX && window.BX.PopupFormSubmit) {
		return;
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
			wait,
			vpf = window.VilmedPopupForm;

		if (vpf) {
			vpf.bindClearOnInput(form);
			if (!vpf.validate(form)) {
				return;
			}
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
				var parsed = vpf ? vpf.parseJsonResponse(response) : response;

				if (!parsed) {
					if (vpf) {
						vpf.showSummary(alert, "Не удалось отправить форму. Попробуйте ещё раз.", "bad");
					}
					BX.closeWait(popup, wait);
					return;
				}

				if (parsed.success) {
					if (vpf) {
						vpf.clearErrors(form);
						vpf.showSummary(alert, parsed.success.text, "good");
					} else if (alert) {
						BX.adjust(alert, {html: "<span class='alertMsg good'><i class='fa fa-check'></i><span class='text'>" + parsed.success.text + "</span></span>"});
					}
					if (typeof ym === "function") {
						ym(55225453, "reachGoal", "ZaprositCenuAnalog2109231355");
					}
					BX.adjust(target, {props: {disabled: true}});
				} else if (parsed.error) {
					if (vpf) {
						vpf.applyServerErrors(form, parsed.error.text);
					} else if (alert) {
						BX.adjust(alert, {html: "<span class='alertMsg bad'><i class='fa fa-exclamation-triangle'></i><span class='text'>" + parsed.error.text + "</span></span>"});
					}
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
				if (vpf) {
					vpf.showSummary(alert, "Ошибка соединения. Проверьте интернет и попробуйте снова.", "bad");
				}
				BX.closeWait(popup, wait);
			}
		});
	};
})();
