/**
 * prime.alerts — live notice under email fields
 * Default: registration + checkout (SOA), not 1-click / lead forms
 *   (1-click uses technical_boc — no account from form email).
 * Config.noticeEverywhere=true — any email field except we still wait for complete address.
 */
(function () {
	var cfg = window.PRIME_ALERTS;
	if (!cfg || cfg.enabled === false) return;

	var providers = cfg.providers || [];
	var everywhere = cfg.noticeEverywhere === true;
	var timers = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
	var DEBOUNCE_MS = 650;

	function domainOf(email) {
		email = String(email || '').trim().toLowerCase();
		var at = email.lastIndexOf('@');
		return at > 0 ? email.slice(at + 1) : '';
	}

	/** Enough of an address to judge zone (local@domain.tld), not mid-typing. */
	function looksComplete(email) {
		email = String(email || '').trim();
		if (!email) return false;
		return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(email);
	}

	function isAllowed(email) {
		email = String(email || '').trim().toLowerCase();
		if (!email) return true;
		var domain = domainOf(email);
		if (!domain) return true;
		if (/\.(ru|su)$/.test(domain)) return true;
		for (var i = 0; i < providers.length; i++) {
			var p = providers[i];
			if (domain === p || domain.slice(-(p.length + 1)) === '.' + p) return true;
		}
		return false;
	}

	function fieldWrap(inp) {
		return inp.closest(
			'.bx-soa-customer-field, .form-group, .soa-property-container, .field, .row, .form-input, tr, label'
		) || inp.parentNode;
	}

	function isLeadForm(inp, form, idText) {
		// «Заказать товар» = altop:forms (under_order), не регистрация и не SOA
		if (form && /^under_order_/i.test(form.id || '')) return true;
		if (/under_order|altop\/forms\/script\.php/i.test(idText || '')) return true;
		if (form && /altop\/forms\/script\.php/i.test(form.action || '')) return true;
		return false;
	}

	function isBuyOneClick(inp, form, idText) {
		if (isLeadForm(inp, form, idText)) return true;
		if (inp && inp.closest('[id*="boc_"], [id*="BOC"]')) {
			var pop = inp.closest('[id*="boc_"], form[id*="boc_"]');
			if (pop) return true;
		}
		if (form && /^boc_/i.test(form.id || '')) return true;
		if (form && /boc/i.test(form.id || '')) return true;
		if (/buy\.one\.click|\/boc_|1cb|one.?click/i.test(idText || '')) return true;
		if (form && /buy\.one\.click/i.test(form.action || '')) return true;
		return false;
	}

	function formBlob(inp) {
		var form = inp.form || inp.closest('form');
		if (!form) {
			var popup = inp.closest('.popup-window, .bx-modal, .modal, [id*="boc_"], [id*="BOC"]');
			return {
				form: null,
				text: ((popup && popup.id) || '') + ' ' + ((popup && popup.className) || '')
			};
		}
		var parts = [
			form.id || '',
			form.name || '',
			form.action || '',
			form.className || '',
			form.getAttribute('data-action') || ''
		];
		var actionInput = form.querySelector('input[name="ACTION"], input[name="action"], input[name="register"], input[name="REGISTER"]');
		if (actionInput) {
			parts.push(actionInput.name || '', actionInput.value || '');
		}
		return { form: form, text: parts.join(' ') };
	}

	/**
	 * @returns {'signup'|'checkout'|null}
	 */
	function contextFor(inp) {
		if (!inp) return null;

		var blob = formBlob(inp);
		var form = blob.form;
		var id = blob.text;

		// «Заказать товар» / 1 клик — учётка с формы не создаётся
		if (isBuyOneClick(inp, form, id)) {
			return everywhere ? 'signup' : null;
		}

		if (inp.closest('#bx-soa-order, #bx-soa-properties, #bx-soa-main-notifications')) {
			return 'checkout';
		}
		if (document.getElementById('bx-soa-order') && inp.closest('form[name="ORDER_FORM"], #bx-soa-order')) {
			return 'checkout';
		}

		// Не матчить «under_order» по подстроке ORDER
		if (/\bORDER_FORM\b|bx-soa|sale\.order\.ajax|\/order\/|checkout/i.test(id)) {
			return 'checkout';
		}
		if (form && form.querySelector && form.querySelector('input[name="ORDER_PROP_EMAIL"], input[name^="ORDER_PROP_"]')) {
			if (inp.name && /^ORDER_PROP_/i.test(inp.name)) {
				return 'checkout';
			}
		}

		if (/regist|signup|AUTH_FORM|bx-auth|personal\/register|USER_REGISTER/i.test(id)) {
			return 'signup';
		}
		if (form) {
			if (form.querySelector('input[name="REGISTER[LOGIN]"], input[name="REGISTER[EMAIL]"], input[name="UF_EMAIL"]')) {
				return 'signup';
			}
			if (form.querySelector('input[name="register_submit_button"], input[name="register"], button[name="register"]')) {
				return 'signup';
			}
			var emailName = (inp.name || '').toLowerCase();
			if ((emailName === 'email' || emailName === 'user_email' || emailName === 'register[email]')
				&& form.querySelector('input[name="PASSWORD"], input[name="REGISTER[PASSWORD]"], input[type="password"]')
				&& form.querySelector('input[name="LOGIN"], input[name="REGISTER[LOGIN]"], input[name="USER_LOGIN"]')) {
				return 'signup';
			}
		}

		if (everywhere) {
			return 'signup';
		}
		return null;
	}

	function noticeHtml(ctx) {
		return ctx === 'checkout' ? (cfg.noticeCheckout || '') : (cfg.noticeSignup || '');
	}

	function ensureBox(inp) {
		var wrap = fieldWrap(inp);
		if (!wrap || !wrap.parentNode) return null;
		var anchor = (wrap.classList && wrap.classList.contains('form-input')) ? (wrap.parentNode || wrap) : wrap;
		var next = anchor.nextElementSibling;
		if (next && next.classList && next.classList.contains('prime-alerts-live-notice')) {
			return next;
		}
		var box = document.createElement('div');
		box.className = 'prime-alerts-live-notice';
		box.setAttribute('aria-live', 'polite');
		box.style.display = 'none';
		if (anchor.nextSibling) {
			anchor.parentNode.insertBefore(box, anchor.nextSibling);
		} else {
			anchor.parentNode.appendChild(box);
		}
		return box;
	}

	function isEmailInput(inp) {
		if (!inp || inp.tagName !== 'INPUT') return false;
		var type = (inp.type || '').toLowerCase();
		var name = (inp.name || '').toLowerCase();
		var auto = (inp.getAttribute('autocomplete') || '').toLowerCase();
		var id = (inp.id || '').toLowerCase();
		if (type === 'email') return true;
		if (auto === 'email') return true;
		if (name === 'user_email' || name === 'email' || name.indexOf('email') >= 0) return true;
		if (id.indexOf('email') >= 0) return true;
		var wrap = fieldWrap(inp);
		var label = wrap ? wrap.textContent : '';
		if (/e-?mail/i.test(label) && type === 'text') return true;
		return false;
	}

	function policyEnabledFor(ctx) {
		if (ctx === 'checkout') return cfg.policyOrder !== false;
		if (ctx === 'signup') return cfg.policyRegister !== false;
		return false;
	}

	function hideBox(box) {
		if (box) box.style.display = 'none';
	}

	function showNotice(inp, ctx, box) {
		if (!box.getAttribute('data-filled')) {
			box.innerHTML = noticeHtml(ctx);
			box.setAttribute('data-filled', '1');
			box.setAttribute('data-ctx', ctx);
		} else if (box.getAttribute('data-ctx') !== ctx) {
			box.innerHTML = noticeHtml(ctx);
			box.setAttribute('data-ctx', ctx);
		}
		box.style.display = '';
	}

	/**
	 * @param {HTMLInputElement} inp
	 * @param {{force?: boolean}} opts force=true on blur — show if looks complete
	 */
	function refreshInput(inp, opts) {
		opts = opts || {};
		if (!isEmailInput(inp)) return;
		var ctx = contextFor(inp);
		var box = ensureBox(inp);
		if (!ctx || !policyEnabledFor(ctx)) {
			hideBox(box);
			return;
		}
		if (!box) return;

		var email = String(inp.value || '').trim();
		if (!email) {
			hideBox(box);
			return;
		}

		// While typing — wait for a finished-looking address
		if (!looksComplete(email)) {
			hideBox(box);
			return;
		}

		if (!isAllowed(email)) {
			showNotice(inp, ctx, box);
		} else {
			hideBox(box);
		}
	}

	function scheduleRefresh(inp) {
		if (!timers) {
			refreshInput(inp);
			return;
		}
		var prev = timers.get(inp);
		if (prev) clearTimeout(prev);
		// hide immediately if incomplete so notice doesn't linger mid-edit
		var email = String(inp.value || '').trim();
		if (!looksComplete(email)) {
			var box = ensureBox(inp);
			hideBox(box);
		}
		timers.set(inp, setTimeout(function () {
			timers.delete(inp);
			refreshInput(inp);
		}, DEBOUNCE_MS));
	}

	function scan(root) {
		root = root || document;
		var nodes = root.querySelectorAll('input[type="email"], input[type="text"], input:not([type])');
		for (var i = 0; i < nodes.length; i++) {
			if (isEmailInput(nodes[i])) refreshInput(nodes[i]);
		}
	}

	function bind() {
		document.addEventListener('input', function (e) {
			if (e.target && e.target.tagName === 'INPUT') scheduleRefresh(e.target);
		}, true);
		document.addEventListener('change', function (e) {
			if (e.target && e.target.tagName === 'INPUT') refreshInput(e.target);
		}, true);
		document.addEventListener('blur', function (e) {
			if (e.target && e.target.tagName === 'INPUT') refreshInput(e.target, { force: true });
		}, true);
		scan();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}

	if (window.BX && BX.addCustomEvent) {
		BX.addCustomEvent('onAjaxSuccess', function () { scan(); });
		BX.addCustomEvent('onFrameDataReceived', function () { scan(); });
	}
	setInterval(function () { scan(); }, 1500);
})();
