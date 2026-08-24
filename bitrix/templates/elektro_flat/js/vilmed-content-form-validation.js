(function(window, document) {
	'use strict';

	function trim(value) {
		return String(value || '').trim();
	}

	function clearErrors(form) {
		var nodes = form.querySelectorAll('.field.has-error, .hint_agreement.has-error');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].classList.remove('has-error');
		}

		var errors = form.querySelectorAll('.field-error');
		for (var j = 0; j < errors.length; j++) {
			errors[j].parentNode.removeChild(errors[j]);
		}

		var summary = form.querySelector('.vilmed-form-summary');
		if (summary) {
			summary.innerHTML = '';
			summary.className = 'vilmed-form-summary';
		}
	}

	function ensureFieldError(field, message) {
		var err = field.querySelector('.field-error');
		if (!err) {
			err = document.createElement('div');
			err.className = 'field-error';
			err.setAttribute('role', 'alert');
			field.appendChild(err);
		}
		err.textContent = message;
	}

	function showFieldError(form, fieldName, message) {
		var input = form.querySelector('[name="' + fieldName + '"]');
		if (!input) {
			return false;
		}

		var field = input.closest('.field');
		if (!field) {
			return false;
		}

		field.classList.add('has-error');
		ensureFieldError(field, message);
		return true;
	}

	function showAgreementError(form, selector, message) {
		var agreement = form.querySelector(selector || '.hint_agreement');
		if (!agreement) {
			return;
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
	}

	function showSummary(form, message, type) {
		var summary = form.querySelector('.vilmed-form-summary');
		if (!summary) {
			summary = document.createElement('div');
			summary.className = 'vilmed-form-summary';
			form.insertBefore(summary, form.firstChild);
		}

		var icon = type === 'good' ? 'fa-check-circle' : 'fa-exclamation-circle';
		var css = type === 'good' ? 'good' : 'bad';
		summary.innerHTML = '<span class="alertMsg ' + css + ' vilmed-form-alert" role="alert"><i class="fa ' + icon + '"></i><span class="text">' + message + '</span></span>';
	}

	function scrollToFirstError(form) {
		var first = form.querySelector('.field.has-error, .hint_agreement.has-error');
		if (first && first.scrollIntoView) {
			first.scrollIntoView({behavior: 'smooth', block: 'center'});
		}
	}

	function validate(form, rules) {
		clearErrors(form);
		var hasErrors = false;

		(rules.fields || []).forEach(function(rule) {
			var input = form.querySelector('[name="' + rule.name + '"]');
			if (!input) {
				return;
			}

			var value = trim(input.value);
			if (rule.required && !value) {
				showFieldError(form, rule.name, rule.message);
				hasErrors = true;
				return;
			}

			if (rule.minLength && value && value.length < rule.minLength) {
				showFieldError(form, rule.name, rule.minLengthMessage || rule.message);
				hasErrors = true;
			}
		});

		if (rules.confirmPassword) {
			var password = form.querySelector('[name="' + rules.confirmPassword.password + '"]');
			var confirm = form.querySelector('[name="' + rules.confirmPassword.confirm + '"]');
			if (password && confirm && trim(password.value) && trim(confirm.value) && password.value !== confirm.value) {
				showFieldError(form, rules.confirmPassword.confirm, rules.confirmPassword.message);
				hasErrors = true;
			}
		}

		if (rules.personalDataInput) {
			var personalData = form.querySelector(rules.personalDataInput);
			if (personalData && personalData.value !== 'Y') {
				showAgreementError(form, rules.agreement, rules.personalDataMessage);
				hasErrors = true;
			}
		}

		if (hasErrors) {
			showSummary(form, rules.summaryMessage || 'Проверьте выделенные поля');
			scrollToFirstError(form);
		}

		return !hasErrors;
	}

	function bindClearOnInput(form, rules) {
		form.addEventListener('input', function(e) {
			var target = e.target;
			if (!target || !target.name) {
				return;
			}

			var field = target.closest('.field');
			if (field && field.classList.contains('has-error')) {
				field.classList.remove('has-error');
				var err = field.querySelector('.field-error');
				if (err) {
					err.parentNode.removeChild(err);
				}
			}
		});

		if (rules.personalDataInput) {
			var checkbox = form.querySelector((rules.agreement || '.hint_agreement') + ' .input-checkbox');
			if (checkbox) {
				checkbox.addEventListener('click', function() {
					var agreement = form.querySelector(rules.agreement || '.hint_agreement');
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
		}
	}

	window.VilmedContentForm = {
		init: function(formSelector, rules) {
			var form = typeof formSelector === 'string' ? document.querySelector(formSelector) : formSelector;
			if (!form) {
				return;
			}

			rules = rules || {};
			bindClearOnInput(form, rules);

			form.addEventListener('submit', function(e) {
				if (!validate(form, rules)) {
					e.preventDefault();
				}
			});
		}
	};
})(window, document);
