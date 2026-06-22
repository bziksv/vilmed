(function(BX, $) {

	const Plugin = BX.namespace('YandexMarket.Plugin');
	const Input = BX.namespace('YandexMarket.Ui.Input');

	const constructor = Input.DependField = Plugin.Base.extend({

		defaults: {
			depend: null,
			headingElement: '.heading',
			siblingElement: 'tr',
			formElement: 'form',
			inputElement: 'input, select, textarea',
			prevAdditionElement: '.js-depend-field-prev-addition',
			nextAdditionElement: '.js-depend-field-next-addition',
		},

		initialize: function() {
			this.callParent('initialize', constructor);
			this.bind();

			if (this.$el.hasClass('js-plugin-delayed')) {
				this.update();
			}
		},

		destroy: function() {
			this.unbind();
			this.callParent('destroy', constructor);
		},

		bind: function() {
			this.handleDependChange(true);
		},

		unbind: function() {
			this.handleDependChange(false);
		},

		handleDependChange: function(dir) {
			const fields = this.getDependElements();

			fields[dir ? 'on' : 'off']('change', $.proxy(this.onDependChange, this));
		},

		onDependChange: function() {
			this.update();
		},

		update: function() {
			const isMatch = this.resolveDependRules();

			if (this.alreadyView(isMatch)) { return; }

			this.toggleView(isMatch);
			this.toggleAdditionView(isMatch);
			this.toggleHeaderView(isMatch);
			this.fireChange();
		},

		alreadyView: function(isMatch) {
			return this.$el.hasClass('is--hidden') === !isMatch;
		},

		toggleView: function(isMatch) {
			this.$el.toggleClass('is--hidden', !isMatch);
		},

		toggleAdditionView: function(isMatch) {
			const prev = this.getElement('prevAddition', this.$el, 'prev');
			const next = this.getElement('nextAddition', this.$el, 'next');

			prev.toggleClass('is--hidden', !isMatch);
			next.toggleClass('is--hidden', !isMatch);
		},

		toggleHeaderView: function(isMatch) {
			const heading = this.getHeading();
			const isHidden = heading.hasClass('is--hidden');

			if (isHidden === !isMatch) { return; }

			const siblings = this.getSiblingsUnderHeading(heading).not('.is--hidden');

			heading.toggleClass('is--hidden', siblings.length === 0);
		},

		getHeading: function() {
			return this.getElement('heading', this.$el, 'prevAll').first();
		},

		getSiblingsUnderHeading: function(heading) {
			const headerSelector = this.getElementSelector('heading');
			const fieldSelector = this.getElementSelector('sibling');
			let sibling = heading;
			let result = $();

			do {
				sibling = sibling.next();

				if (sibling.is(headerSelector)) { break; }

				if (sibling.is(fieldSelector)) {
					result = result.add(sibling);
				}
			} while (sibling.length !== 0);

			return result;
		},

		fireChange: function() {
			this.getElement('input').trigger('change');
		},

		resolveDependRules: function() {
			return this.testRules(this.options.depend, this.getDependFields());
		},

		testRules: function(rules, fields) {
			const isDependAny = (rules['LOGIC'] === 'OR');

			for (const [key, rule] of Object.entries(rules)) {
				if (key === 'LOGIC') { continue; }

				const field = fields[key];
				const match = $.isPlainObject(field)
					? this.testRules(rule, field)
					: this.isMatchRule(rule, this.getFieldValue(field, key));

				if (match === isDependAny) {
					return isDependAny;
				}
			}

			return !isDependAny;
		},

		getFieldValue: function(field, name) {
			let result;

			if (this.isHiddenField(field) && !$.contains(this.el, field[0])) { return null; }

			switch (this.getFieldType(field, name))
			{
				case 'plugin':
					result = !Plugin.manager.getInstance(field).isEmpty();
				break;

				case 'complex':
					result = this.getComplexValue(field, name);
				break;

				case 'hidden':
					if (field.length > 1) { // is checkbox sibling
						result = this.getFieldValue(field.slice(1));
					} else {
						result = field.val();
					}
				break;

				case 'checkbox':
					result = [];
					field.each(function() { if (this.checked) { result.push(this.value); } });
				break;

				case 'radio':
					field.each(function() { if (this.checked) { result = this.value; } });
				break;

				default:
					result = field.val();
				break;
			}

			return result;
		},

		isHiddenField: function(field) {
			return this.getElement('sibling', field, 'closest').hasClass('is--hidden');
		},

		getFieldType: function(field, name) {
			const pluginName = field.data('plugin');
			const selfName = field.data('name');
			const plugin = pluginName && Plugin.manager.getPlugin(pluginName);
			let result = (field.prop('tagName') || '').toLowerCase();

			if (result === 'input') {
				result = (field.prop('type') || '').toLowerCase();
			}

			if (plugin && ('isEmpty' in plugin.prototype)) {
				result = 'plugin';
			} else if (selfName != null && selfName !== name && selfName.indexOf('[' + name + ']') === 0) {
				result = 'complex';
			}

			return result;
		},

		getComplexValue: function(field, baseName) {
			const nameStart = '[' + baseName + ']';
			const result = {};

			for (let childIndex = 0; childIndex < field.length; childIndex++) {
				const child = field.eq(childIndex);
				const childFullName = child.data('name');

				if (childFullName == null || childFullName.indexOf(nameStart) !== 0) { continue; }

				const childName = childFullName.substring(nameStart.length);
				result[childName] = this.getFieldValue(child, childFullName);
			}

			return result;
		},

		isMatchRule: function(rule, value) {
			if (rule['RULE'] === 'EMPTY') {
				return (this.testIsEmpty(value) === rule['VALUE']);
			}

			if (rule['RULE'] === 'ANY') {
				return this.applyRuleAny(rule['VALUE'], value);
			}

			if (rule['RULE'] === 'EXCLUDE') {
				return !this.applyRuleAny(rule['VALUE'], value);
			}

			return true;
		},

		testIsEmpty: function(value) {
			let result = true;

			if (Array.isArray(value)) {
				for (const one of value) {
					if (!this.testIsEmpty(one)) {
						result = false;
						break;
					}
				}
			} else if ($.isPlainObject(value)) {
				for (const key in value) {
					if (!value.hasOwnProperty(key)) { continue; }

					if (!this.testIsEmpty(value[key])) {
						result = false;
						break;
					}
				}
			} else {
				result = (!value || value === '0');
			}

			return result;
		},

		applyRuleAny: function(ruleValue, formValue) {
			const isRuleMultiple = Array.isArray(ruleValue);
			const isFormMultiple = Array.isArray(formValue);
			let formIndex;
			let formItem;
			let result = false;

			if (isFormMultiple && isRuleMultiple) {
				for (formIndex = formValue.length - 1; formIndex >= 0; --formIndex) {
					formItem = formValue[formIndex];

					if (this.testInArray(formItem, ruleValue)) {
						result = true;
						break;
					}
				}
			} else if (isFormMultiple) {
				result = this.testInArray(ruleValue, formValue);
			} else if (isRuleMultiple) {
				result = this.testInArray(formValue, ruleValue);
			} else {
				// noinspection EqualityComparisonWithCoercionJS
				result = (formValue == ruleValue);
			}

			return result;
		},

		testInArray: function(needle, haystack) {
			for (let i = haystack.length - 1; i >= 0; i--) {
				// noinspection EqualityComparisonWithCoercionJS
				if (haystack[i] == needle) {
					return true;
				}
			}

			return false;
		},

		getDependElements: function() {
			return this.extractDependElements(this.getDependFields());
		},

		extractDependElements: function(fields) {
			let result = $();

			for (const [, field] of Object.entries(fields)) {
				if ($.isPlainObject(field)) {
					result = result.add(this.extractDependElements(field));
					continue;
				}

				result = result.add(field);
			}

			return result;
		},

		getDependFields: function() {
			return this.compileDependFields(this.options.depend);
		},

		compileDependFields: function(depend) {
			const result = {};

			for (const [key, rule] of Object.entries(depend)) {
				if (key === 'LOGIC') { continue; }

				if (/^\d+$/.test(key)) {
					result[key] = this.compileDependFields(rule);
					continue;
				}

				result[key] = this.getField(key);
			}

			return result;
		},

		getField: function(selector) {
			if (selector.substring(0, 1) === '#') {
				return $(selector);
			}

			if (selector.substring(0, 1) === '@') {
				return $(`[name="${selector.substring(1)}"]`);
			}

			return this.getFormField(selector);
		},

		getFormField: function(name) {
			const form = this.getElement('form', this.$el, 'closest');
			const isForm = form.is('form');
			const nameMultiple = name + '[]';
			const nameMultipleSecond = name + '[0]';

			if (isForm && form[0][name] != null) {
				return $(form[0][name]);
			}

			if (isForm && form[0][nameMultiple] != null) {
				return $(form[0][nameMultiple]);
			}

			if (isForm && form[0][nameMultipleSecond] != null) {
				return $(form[0][nameMultipleSecond]);
			}

			const variants = [
				'[data-name="' + name + '"]',
				'[data-name^="[' + name + ']"]',
			];

			for (const variant of variants) {
				const field = form.find(variant);

				if (field.length > 0) { return field; }
			}

			return $();
		},

	}, {
		dataName: 'UiInputDependField'
	});

})(BX, jQuery);