const $ = window.YMarketJQuery || window.jQuery;
const BX = window.BX;
const Plugin = BX.namespace('YandexMarket.Plugin');
const Reference = BX.namespace('YandexMarket.Field.Reference');
const Source = BX.namespace('YandexMarket.Source');
const utils = BX.namespace('YandexMarket.Utils');

export class Node extends Reference.Base {

	static dataName = 'FieldParamNode';
	static defaults = Object.assign({}, Reference.Base.prototype.defaults, {
		type: null,
		valueType: 'string',
		required: false,
		copyType: null,
		linkedSources: [ 'iblock_property_feature' ],

		managerElement: '.js-param-manager',

		inputElement: '.js-param-node__input',
		sourceElement: '.js-param-node__source',
		fieldWrapElement: '.js-param-node__field-wrap',
		fieldElement: '.js-param-node__field',
		templateButtonElement: '.js-param-node__template-button',

		fieldTextTemplate: '<input class="b-param-table__input js-param-node__input js-param-node__field" type="text" />',
		fieldFormulaTemplate: '<div class="b-input-formula js-param-node__field-wrap" data-plugin="Ui.Input.Formula">' +
			'<select class="b-input-formula__function b-param-table__control js-param-node__input js-input-formula__function" data-complex="FUNCTION"></select>' +
			'<div class="b-input-formula__parts-wrap">' +
				'<select class="b-input-formula__parts b-param-table__input js-param-node__field js-param-node__input js-input-formula__parts" data-complex="PARTS" size="1"></select>' +
			'</div>' +
			'<button class="b-input-formula__dropdown b-param-table__control adm-btn js-input-formula__dropdown" type="button">...</button>' +
		'</div>',
		fieldSelectTemplate: '<select class="b-param-table__input js-param-node__input js-param-node__field" data-plugin="Ui.Input.TagInput" data-width="100%" data-tags="false"></select>',
		fieldTemplateTemplate: '<div class="b-control-group js-param-node__field-wrap" data-plugin="Ui.Input.Template">' +
			'<input class="b-control-group__item pos--first b-param-table__input js-param-node__input js-param-node__field js-input-template__origin" type="text" />' +
			'<button class="b-control-group__item pos--last width--by-content adm-btn around--control js-input-template__dropdown" type="button">...</button>' +
		'</div>',

		langPrefix: 'YANDEX_MARKET_FIELD_PARAM_',
		lang: {}
	});

	_manager;
	_lastSource;
	_fieldValueUserInput;

	initialize() {
		super.initialize();
		this.bind();
		this.initValueUi();
	}

	destroy() {
		this.unbind();
		super.destroy();
	}

	bind() {
		this.handleSourceChange(true);
		this.handleInputChange(true);
		this.handleLinkedSourceFieldChange(true);
	}

	unbind() {
		this.handleSourceChange(false);
		this.handleInputChange(false);
		this.handleLinkedSourceFieldChange(false);
	}

	handleSourceChange(dir) {
		const sourceElement = this.getElement('source');

		sourceElement[dir ? 'on' : 'off']('change keyup', $.proxy(this.onSourceChange, this));
	}

	handleParentField(field, dir) {
		this.handleCopyTypeFieldChange(field, dir);
		this.handleCopyTypeSelfFieldInput(dir);
	}

	handleCopyTypeFieldChange(parentField, dir) {
		const type = this.options.copyType;

		if (type != null) {
			const typeCollection = parentField.getTypeCollection(type);

			typeCollection[dir ? 'on' : 'off']('change keyup', this.getElementSelector('field'), $.proxy(this.onCopyTypeFieldChange, this));
		}
	}

	handleCopyTypeSelfFieldInput(dir) {
		const type = this.options.copyType;

		if (type != null) {
			this.$el[dir ? 'on' : 'off']('input paste', this.getElementSelector('field'), $.proxy(this.onCopyTypeSelfFieldInput, this));
		}
	}

	handleLinkedSourceFieldChange(dir) {
		if (this.options.linkedSources == null && dir) { return; }

		this.$el[dir ? 'on' : 'off']('change', this.getElementSelector('field'), $.proxy(this.onLinkedSourceFieldChange, this));
	}

	handleInputChange(dir) {
		const inputSelector = this.getElementSelector('input');

		this.$el[dir ? 'on' : 'off']('change', inputSelector, $.proxy(this.onInputChange, this));
	}

	onSourceChange(evt) {
		this.refreshField(evt.target.value);
	}

	onCopyTypeFieldChange(evt) {
		const input = evt.currentTarget;

		this.copyFieldValue(input);
	}

	onCopyTypeSelfFieldInput(evt) {
		const input = evt.currentTarget;

		this._fieldValueUserInput = (input.value !== '');
	}

	onLinkedSourceFieldChange(evt) {
		const source = this.getElement('source').val();

		if (this.options.linkedSources.indexOf(source) === -1) { return; }

		this.$el.trigger('FieldParamNodeLinkedChange', {
			node: this,
			source: source,
			field: evt.target.value,
		});
	}

	onInputChange() {
		this.$el.trigger('FieldParamNodeChange');
	}

	preselect() {
		if (this.getElement('source').val() !== 'recommendation') { return; }

		const field = this.getElement('field');
		const options = field.find('option');

		if (field.data('type') !== 'select') { return; }

		for (let i = 0; i < options.length; ++i) {
			let option = options[i];

			if (option.value === '') { continue; }

			if (!option.selected) {
				option.selected = true;
				field.trigger('change');
			}

			break;
		}
	}

	setValue(value: Object) : void {
		if (value['TYPE'] != null) {
			const sourceElement = this.getElement('source');

			sourceElement.val(value['TYPE']);
			this.refreshField(sourceElement.val());
		}

		super.setValue(value);
		this.reflowFieldSelected(value);
	}

	syncLinked(source, field) {
		this.syncLinkedSource(source) && this.syncLinkedField(field);
	}

	syncLinkedSource(source) {
		const element = this.getElement('source');
		const options = element.find('option');
		let found = false;

		if (element.val() === source) { return true; }

		for (let i = 0; i < options.length; ++i) {
			let option = options[i];

			if (option.value !== source) { continue; }

			found = true;

			if (!option.selected) {
				option.selected = true;
				element.trigger('change'); // force reflow field
			}

			break;
		}

		return found;
	}

	syncLinkedField(value) {
		const field = this.getElement('field');
		const options = field.find('option');
		const marker = value.replace(/\.[^.]+$/, '.');
		let found = false;

		if (marker === '') { return found; }
		if (field.data('type') !== 'select') { return found; }

		for (let i = 0; i < options.length; ++i) {
			let option = options[i];

			if (option.value.indexOf(marker) !== 0) { continue; }

			found = true;

			if (!option.selected) {
				option.selected = true;
				field.trigger('change'); // force reflow select2
			}

			break;
		}

		return found;
	}

	clear() {
		super.clear();
		this._fieldValueUserInput = false;
	}

	setParentField(field) {
		const previousParent = this.getParentField();

		if (previousParent != null) {
			this.handleParentField(previousParent, false);
		}

		if (field != null) {
			this.handleParentField(field, true);
		}

		super.setParentField(field);
	}

	isFieldValueUserInput(field) {
		if (this._fieldValueUserInput == null) {
			const fieldElement = field || this.getElement('field');
			const fieldValue = fieldElement.val();

			this._fieldValueUserInput = (fieldValue != null && fieldValue !== '');
		}

		return this._fieldValueUserInput;
	}

	copyFieldValue(fromElement) {
		const fieldElement = this.getElement('field');
		const fieldTagName = (fieldElement.prop('tagName') || '').toLowerCase();
		const fromTagName = (fromElement.tagName || '').toLowerCase();
		let fromValue;
		let option;

		if (fieldTagName === 'input' && fromTagName === 'select' && !this.isFieldValueUserInput(fieldElement)) { // support copy only in input from select
			option = $('option', fromElement).filter(':selected');

			if (option.val()) { // is not placeholder
				fromValue = option.text();
			}

			if (fromValue != null) {
				fromValue = fromValue.replace(/^\[\d+]/, '').trim(); // remove id

				fieldElement.val(fromValue);
			}
		}
	}

	refreshField(typeId) {
		if (this._lastSource != null && this._lastSource === typeId) { return; }

		const manager = this.getManager();
		const type = manager.getType(typeId);

		this._lastSource = typeId;
		this.updateField(type, manager);
	}

	updateField(type, manager) {
		let fieldElement = this.getElement('field');
		let fieldEnumList;
		let fieldEnum;
		let fieldValue;
		let i;
		let fieldType = (fieldElement.data('type') || '').toLowerCase();
		let needType = type['CONTROL'] || 'select';
		let content;

		if (needType === 'select') {
			fieldEnumList = this.getFieldList(type['ID'], manager);
			fieldValue = fieldElement.val();
			content = '';

			if (fieldEnumList != null && fieldEnumList.length > 0) {
				if (!this.options.required) {
					content += '<option value="">' + this.getLang('SELECT_PLACEHOLDER') + '</option>';
				}

				for (i = 0; i < fieldEnumList.length; i++) {
					fieldEnum = fieldEnumList[i];

					if (fieldEnum['DEPRECATED'] && fieldValue !== fieldEnum['ID']) { continue; }

					content +=
						'<option ' +
							'value="' + fieldEnum['ID'] + '"' +
							(fieldValue === fieldEnum['ID'] ? ' selected' : '') +
						'>'
						+ utils.escape(fieldEnum['VALUE'])
						+ '</option>';
				}
			}
		}

		if (fieldType !== needType) {
			fieldElement = this.renderField(fieldElement, needType);
		}

		if (content != null) {
			fieldElement.html(content);
		}
	}

	renderField(field, type) {
		let templateKey = 'field' + type.substring(0, 1).toUpperCase() + type.substring(1);
		let template = this.getTemplate(templateKey);
		let fieldSelector = this.getElementSelector('field');
		let inputSelector = this.getElementSelector('input');
		let oldWrap = this.getElement('fieldWrap', field, 'closest');
		let newWrap = $(template);
		let newField = newWrap.filter(fieldSelector);
		let newFieldInputs = newField.add(newWrap.find(inputSelector));

		if (oldWrap.length === 0) { oldWrap = field; }
		if (newField.length === 0) { newField = newWrap.find(fieldSelector); }

		this.destroyValueUi(oldWrap);

		this.copyAttrList(field, newFieldInputs, ['name', 'data-name']);
		newField.data('type', type);

		newWrap.insertAfter(oldWrap);
		oldWrap.remove();

		this.initValueUi(newWrap);

		this._fieldValueUserInput = false;

		return newField;
	}

	destroyValueUi(newField) {
		let value = newField || this.getElementFieldWrap();

		Plugin.manager.destroyElement(value);
	}

	initValueUi(newField) {
		let value = newField || this.getElementFieldWrap();
		let plugins = Plugin.manager.initializeElement(value);
		let firstPlugin = plugins[0];

		if (firstPlugin != null) {
			firstPlugin.setOptions({
				sourceManager: this.getManager(),
				sourceType: this.getElement('source').val(),
				nodeType: this.options.type
			});

			if (typeof firstPlugin.render === 'function') {
				firstPlugin.render();
			}
		}
	}

	reflowFieldSelected(value: Object) : void {
		const fieldWrapper = this.getElementFieldWrap();
		const plugin = Plugin.manager.getInstance(fieldWrapper);

		if (plugin != null && plugin.setValue != null) {
			plugin.setValue(this.sliceOnlyFieldValue(value));
			return;
		}

		const field = this.getElementField();

		if (field.hasClass('select2-hidden-accessible')) {
			field.trigger('change.select2');
		}
	}

	sliceOnlyFieldValue(value: Object) {
		if (value['FIELD'] != null) {
			return value['FIELD'];
		}

		const fieldValue = {};

		for (const [key, item] of Object.entries(value)) {
			const matches = /^\[FIELD]\[(.*?)]/.exec(key);

			if (matches == null) { continue; }

			fieldValue[matches[1]] = item;
		}

		return fieldValue;
	}

	getElementField() {
		return this.getElement('field');
	}

	getElementFieldWrap() {
		let result = this.getElement('fieldWrap');

		return (result.length > 0 ? result : this.getElementField());
	}

	copyAttrList(fromElement, toElements, attrList) {
		let fromValues = this.readAttrList(fromElement, attrList);
		let toIndex;
		let toElement;

		for (toIndex = toElements.length - 1; toIndex >= 0; toIndex--) {
			toElement = toElements.eq(toIndex);
			this.writeAttrList(toElement, fromValues);
		}
	}

	readAttrList(field, attributeNames) {
		let complex = field.data('complex');
		let complexFull = complex ? '[' + complex + ']' : null;
		let complexPosition;
		let result = {};
		let attributeName;
		let attributeValue;
		let i;

		for (i = attributeNames.length - 1; i >= 0; i--) {
			attributeName = attributeNames[i];
			attributeValue = field.attr(attributeName);

			if (attributeValue == null) { continue; }

			if (attributeName.indexOf('name') !== -1) {
				attributeValue = attributeValue.replace(/\[]$/, '');

				if (complexFull) {
					complexPosition = attributeValue.indexOf(complexFull);

					if (complexPosition !== -1 && attributeValue.length === complexPosition + complexFull.length) {
						attributeValue = attributeValue.substring(0, complexPosition);

						if (attributeName === 'data-name') {
							attributeValue = attributeValue.replace(/^\[([^\]]+)]$/, '$1');
						}
					}
				}
			}

			result[attributeName] = attributeValue;
		}

		return result;
	}

	writeAttrList(field, attributeValues) {
		let complex = field.data('complex');
		let complexFull = complex ? '[' + complex + ']' : '';
		let attributeName;
		let attributeValue;

		for (attributeName in attributeValues) {
			if (!attributeValues.hasOwnProperty(attributeName)) { continue; }

			attributeValue = attributeValues[attributeName];

			if (complexFull && attributeName.indexOf('name') !== -1) {
				if (attributeName === 'data-name' && attributeValue.indexOf('[') !== 0) {
					attributeValue = '[' + attributeValue + ']' + complexFull;
				} else {
					attributeValue += complexFull;
				}
			}

			field.attr(attributeName, attributeValue);
		}
	}

	getFieldList(typeId, manager) {
		let result;

		manager = manager || this.getSourceManager();

		if (typeId === 'recommendation') {
			result = manager.getRecommendationList(this.options.type);
		} else {
			result = manager.getTypeFieldList(typeId, this.options.valueType, this.options.type);
		}

		return result;
	}

	getManager() {
		if (this._manager == null) {
			const element = this.getElement('manager', this.$el, 'closest');
			this._manager = Source.Manager.getInstance(element);
		}

		return this._manager;
	}

}