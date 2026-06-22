import {BasketItemCis} from "./basketitemcis";

const BX = window.BX;
const $ = window.jQuery;
const YandexMarket = BX.namespace('YandexMarket');
const Reference = BX.namespace('YandexMarket.Field.Reference');

const constructor = Reference.Summary.extend({

	defaults: {
		summaryElement: '.js-yamarket-basket-item-cis__summary',
		fieldElement: '.js-yamarket-basket-item-cis__field',
		modalElement: '.js-yamarket-basket-item-cis__modal',
		modalWidth: 400,
		modalHeight: 300,
		requiredTypes: '',
		optionalTypes: '',

		copyElement: '.js-yamarket-basket-item-cis__summary-copy',
		copy: null,

		lang: {},
		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_'
	},

	initialize: function() {
		this.callParent('initialize', constructor);
		this.bind();
	},

	destroy: function() {
		this.unbind();
		this.callParent('destroy', constructor);
	},

	bind: function() {
		this.handleSummaryClick(true);
		this.handleCopyClick(true);
	},

	unbind: function() {
		this.handleSummaryClick(false);
		this.handleCopyClick(false);
	},

	handleSummaryClick: function(dir) {
		const summary = this.getElement('summary');

		summary[dir ? 'on' : 'off']('click', $.proxy(this.onSummaryClick, this));
	},

	handleCopyClick: function(dir) {
		const copy = this.getElement('copy');

		copy[dir ? 'on' : 'off']('click', $.proxy(this.onCopyClick, this));
	},

	onSummaryClick: function(evt) {
		this.openEditModal();
		evt.preventDefault();
	},

	onCopyClick: function(evt) {
		this.copy();
		this.refreshSummary();
		evt.preventDefault();
	},

	updateField: function(modalContent) {
		this.callParent('updateField', [modalContent], constructor);
		this.syncSameBasketItems();
	},

	syncSameBasketItems: function() {
		const ourBasketItem = this.getBasketItem();
		const sameBasketItems = ourBasketItem.getBasket().getBox().getCollection().sameBasketItems(ourBasketItem);

		if (sameBasketItems.length === 1) { return; }

		const fieldName = this.$el.data('name');
		const value = this.getValue();

		for (const siblingBasketItem of sameBasketItems) {
			if (siblingBasketItem === ourBasketItem) { continue; }

			const siblingCis = siblingBasketItem.getChildField(fieldName);

			if (siblingCis == null) { continue; }

			siblingCis.setValue(value);

			if (siblingBasketItem.isPart()) {
				siblingCis.refreshSummary();
			}
		}
	},

	validate: function() {
		const valueList = this.getValue();
		const status = this.getCisStatus(valueList);

		if (status === 'WAIT') {
			throw new Error(this.getLang('REQUIRED'));
		}
	},

	refreshSummary: function() {
		const valueList = this.getValue();
		const status = this.getCisStatus(valueList);
		const statusText = this.getLang('SUMMARY_' + status) || status;
		const summary = this.getElement('summary');

		summary.text(statusText);
		summary.attr('data-status', status);
	},

	getCisStatus: function(valueList) {
		const basketItem = this.getBasketItem();
		const count = basketItem.getCount();
		const [filled, optional]= this.getFilledCount(valueList, count, basketItem.getOffset());
		let result;

		if (filled >= count) {
			result = 'READY';
		} else if (optional >= count) {
			result = 'OPTIONAL';
		} else {
			result = 'WAIT';
		}

		return result;
	},

	getFilledCount: function(valueList, count, offset) {
		const types = this.requiredTypes();
		const optionalTypes = this.optionalTypes();
		let filled = 0;
		let optional = 0;

		for (let i = offset; i < offset + count; ++i) {
			let itemFilled = 0;
			let itemOptional = 0;

			for (const type of types) {
				const name = `[ITEMS][${i}][${type}]`;
				const value = valueList[name] ?? '';

				if (value.trim() !== '') {
					++itemFilled;
					++itemOptional;
				} else if (optionalTypes.indexOf(type) !== -1) {
					++itemOptional;
				}
			}

			if (itemFilled >= types.length) {
				++filled;
			}

			if (itemOptional >= types.length) {
				++optional;
			}
		}

		return [filled, optional];
	},

	getBasketCount: function() {
		return this.getBasketItem().getCount();
	},

	setBasketCount: function(count, offset) {
		this.callField('setBasketCount', [count, offset]);
	},

	requiredTypes: function() {
		const option = this.options.requiredTypes || '';

		return option.split(',');
	},

	optionalTypes: function() {
		const option = this.options.optionalTypes || '';

		return option.split(',');
	},

	copy: function() {
		const copyValues = this.getCopyValue();
		const values = this.getValue();

		Object.assign(values, copyValues);

		this.setValue(values);
	},

	getCopyValue: function() {
		return this.options.copy;
	},

	getFieldPlugin: function() {
		return BasketItemCis;
	},

	getBasketItem: function() {
		return this.getParentField();
	},

	createModal: function() {
		return new YandexMarket.EditDialog({
			'title': this.options.title,
			'draggable': true,
			'resizable': true,
			'buttons': [YandexMarket.EditDialog.btnSave, YandexMarket.EditDialog.btnCancel],
			'width': this.options.modalWidth,
			'height': this.options.modalHeight
		});
	},

}, {
	dataName: 'orderViewBasketItemCisSummary',
	pluginName: 'YandexMarket.OrderView.BasketItemCisSummary',
});

export const BasketItemCisSummary = constructor;