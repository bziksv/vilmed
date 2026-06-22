const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');

const constructor = Reference.Base.extend({

	defaults: {
		inputElement: '.js-yamarket-basket-item-cis__input',
		copyElement: '.js-yamarket-basket-item-cis__copy',
		rowElement: '.js-yamarket-basket-item-cis__row',
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
		this.handleCopyClick(true);
	},

	unbind: function() {
		this.handleCopyClick(false);
	},

	handleCopyClick: function(dir) {
		const copy = this.getElement('copy');

		copy[dir ? 'on' : 'off']('click', $.proxy(this.onCopyClick, this));
	},

	onCopyClick: function(evt) {
		this.copy();
		evt.preventDefault();
	},

	copy: function() {
		const copyValues = this.getCopyValue();
		const values = this.getValue();

		Object.assign(values, copyValues);

		this.setValue(values);
	},

	getCopyValue: function() {
		const parent = this.getParentField();
		let result;

		if (this.options.copy != null) {
			result = this.options.copy;
		} else if (parent != null) {
			result = parent.getCopyValue();
		}

		return result;
	},

	setBasketCount: function(count, offset) {
		const rows = this.getElement('row');

		for (let i = 0; i < rows.length; ++i) {
			const row = rows.eq(i);

			if (i >= offset && i < offset + count) {
				row.removeClass('is--hidden');
				row.find('input').prop('disabled', false);
			} else {
				row.addClass('is--hidden');
				row.find('input').prop('disabled', true);
			}
		}
	},

}, {
	dataName: 'orderViewBasketItemCis',
	pluginName: 'YandexMarket.OrderView.BasketItemCis',
});

export const BasketItemCis = constructor;