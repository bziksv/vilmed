(function(window) {
	'use strict';

	function trim(value) {
		return String(value || '').trim();
	}

	function getFieldLabel(row) {
		var label = row.querySelector('.span1');
		if (!label) {
			return 'Поле';
		}
		return label.textContent.replace(/\*/g, '').trim() || 'Поле';
	}

	function findRowByInputName(form, fieldName) {
		var input = form.querySelector('[name="' + fieldName + '"]');
		if (!input) {
			return null;
		}
		return input.closest('.row');
	}

	function mapMessageToField(message) {
		var lower = message.toLowerCase();

		if (lower.indexOf('персональн') !== -1 || lower.indexOf('соглас') !== -1) {
			return '__agreement__';
		}
		if (lower.indexOf('капч') !== -1 || (lower.indexOf('код') !== -1 && lower.indexOf('картин') !== -1)) {
			return 'CAPTCHA_WORD';
		}
		if (lower.indexOf('телефон') !== -1) {
			return 'PHONE';
		}
		if (lower.indexOf('e-mail') !== -1 || lower.indexOf('email') !== -1 || lower.indexOf('почт') !== -1) {
			return 'EMAIL';
		}
		if (lower.indexOf('сообщен') !== -1) {
			return 'MESSAGE';
		}
		if (lower.indexOf('файл') !== -1 || lower.indexOf('реквизит') !== -1) {
			return 'FILE';
		}
		if (lower.indexOf('имя') !== -1) {
			return 'NAME';
		}

		return null;
	}

	function splitMessages(html) {
		return String(html || '')
			.replace(/<br\s*\/?>/gi, '\n')
			.replace(/<[^>]+>/g, '')
			.split(/\n+/)
			.map(function(item) { return trim(item); })
			.filter(Boolean);
	}

	function ensureRowError(row, message) {
		var span2 = row.querySelector('.span2') || row;
		var err = span2.querySelector('.field-error');
		if (!err) {
			err = document.createElement('div');
			err.className = 'field-error';
			err.setAttribute('role', 'alert');
			span2.appendChild(err);
		}
		err.textContent = message;
	}

	window.VilmedPopupForm = {
		clearErrors: function(form) {
			if (!form) {
				return;
			}

			var rows = form.querySelectorAll('.row.has-error, .hint_agreement.has-error');
			for (var i = 0; i < rows.length; i++) {
				rows[i].classList.remove('has-error');
			}

			var errors = form.querySelectorAll('.field-error');
			for (var j = 0; j < errors.length; j++) {
				errors[j].parentNode.removeChild(errors[j]);
			}

			var alert = form.querySelector('.alert');
			if (alert) {
				alert.innerHTML = '';
			}
		},

		showSummary: function(alertNode, message, type) {
			if (!alertNode) {
				return;
			}

			var icon = type === 'good' ? 'fa-check-circle' : (type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle');
			var css = type === 'good' ? 'good' : (type === 'info' ? 'info' : 'bad');
			alertNode.innerHTML = '<span class="alertMsg ' + css + ' vilmed-form-alert" role="alert"><i class="fa ' + icon + '"></i><span class="text">' + message + '</span></span>';

			if (alertNode.scrollIntoView) {
				alertNode.scrollIntoView({behavior: 'smooth', block: 'nearest'});
			}
		},

		showFieldError: function(form, fieldName, message) {
			if (fieldName === '__agreement__') {
				var agreement = form.querySelector('.hint_agreement');
				if (!agreement) {
					return false;
				}
				agreement.classList.add('has-error');
				var err = agreement.querySelector('.field-error');
				if (!err) {
					err = document.createElement('div');
					err.className = 'field-error';
					err.setAttribute('role', 'alert');
					agreement.appendChild(err);
				}
				err.textContent = message;
				return true;
			}

			var row = findRowByInputName(form, fieldName);
			if (!row) {
				return false;
			}

			row.classList.add('has-error');
			ensureRowError(row, message);
			return true;
		},

		validate: function(form) {
			var hasErrors = false;
			var rows = form.querySelectorAll('.row');

			for (var i = 0; i < rows.length; i++) {
				var row = rows[i];
				if (!row.querySelector('.mf-req')) {
					continue;
				}

				var input = row.querySelector(".span2 input[type='text'], .span2 textarea");
				if (!input) {
					continue;
				}

				if (!trim(input.value)) {
					this.showFieldError(form, input.getAttribute('name'), 'Заполните поле «' + getFieldLabel(row) + '»');
					hasErrors = true;
				}
			}

			var personalData = form.querySelector("input[name='PERSONAL_DATA']");
			if (personalData && personalData.value !== 'Y') {
				this.showFieldError(form, '__agreement__', 'Подтвердите согласие на обработку персональных данных');
				hasErrors = true;
			}

			if (hasErrors) {
				this.showSummary(form.querySelector('.alert'), 'Проверьте выделенные поля', 'bad');
				var first = form.querySelector('.row.has-error, .hint_agreement.has-error');
				if (first && first.scrollIntoView) {
					first.scrollIntoView({behavior: 'smooth', block: 'center'});
				}
			}

			return !hasErrors;
		},

		applyServerErrors: function(form, html) {
			var general = [];
			var messages = splitMessages(html);

			this.clearErrors(form);

			for (var i = 0; i < messages.length; i++) {
				var message = messages[i];
				var field = mapMessageToField(message);
				if (field && this.showFieldError(form, field, message)) {
					continue;
				}
				general.push(message);
			}

			var alert = form.querySelector('.alert');
			if (general.length) {
				this.showSummary(alert, general.join('<br>'), 'bad');
			} else if (messages.length) {
				this.showSummary(alert, 'Проверьте выделенные поля', 'bad');
			}

			var first = form.querySelector('.row.has-error, .hint_agreement.has-error');
			if (first && first.scrollIntoView) {
				first.scrollIntoView({behavior: 'smooth', block: 'center'});
			}
		},

		bindClearOnInput: function(form) {
			if (!form || form.__vilmedPopupFormBound) {
				return;
			}
			form.__vilmedPopupFormBound = true;

			form.addEventListener('input', function(e) {
				var target = e.target;
				if (!target || !target.name) {
					return;
				}

				var row = target.closest('.row');
				if (row && row.classList.contains('has-error')) {
					row.classList.remove('has-error');
					var err = row.querySelector('.field-error');
					if (err) {
						err.parentNode.removeChild(err);
					}
				}
			});

			var checkbox = form.querySelector('.hint_agreement .input-checkbox');
			if (checkbox) {
				checkbox.addEventListener('click', function() {
					var agreement = form.querySelector('.hint_agreement');
					if (!agreement) {
						return;
					}
					agreement.classList.remove('has-error');
					var err = agreement.querySelector('.field-error');
					if (err) {
						err.parentNode.removeChild(err);
					}
				});
			}
		},

		parseJsonResponse: function(response) {
			if (typeof response === 'object' && response !== null) {
				return response;
			}
			if (typeof response !== 'string' || !trim(response)) {
				return null;
			}
			try {
				return JSON.parse(response);
			} catch (e) {
				return null;
			}
		}
	};
})(window);
