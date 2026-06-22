const BX = window.BX;
const $ = window.jQuery;
const Reference = BX.namespace('YandexMarket.Field.Reference');

const constructor = Reference.Complex.extend({

	defaults: {
		selfElement: '.js-yamarket-box',
		titleElement: '.js-yamarket-box__title',
		numberElement: '.js-yamarket-box__number',
		inputElement: '.js-yamarket-box__input',
		childElement: '.js-yamarket-box__child',
		deleteElement: '.js-yamarket-box__delete',

		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BOX_',
		lang: {},
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
		this.handleDeleteClick(true);
	},

	unbind: function() {
		this.handleDeleteClick(false);
	},

	handleDeleteClick: function(dir) {
		const deleteButton = this.getElement('delete');

		deleteButton[dir ? 'on' : 'off']('click', $.proxy(this.onDeleteClick, this));
	},

	onDeleteClick: function(evt) {
		evt.preventDefault();

		if (this.onlyForPart()) {
			if (!confirm(this.getLang('DELETE_PART_BY_MERGE'))) { return; }

			this.getBasket().callItemList('cancelSplit');
			return;
		}

		this.getCollection().deleteItem(this.$el);
	},

	onlyForPart: function() {
		let result = false;

		this.getBasket().callItemList(function(basketItem) {
			if (basketItem.isPart()) {
				result = true;
			}
		});

		return result;
	},

	validate: function() {
		this.callChildList('validate');
	},

	getTitle: function() {
		const title = this.getElement('title');

		return title && title.text().replace(/\s+/g, ' ').trim();
	},

	setIndex: function(index) {
		this.callParent('setIndex', [index], constructor);
		this.updateNumber(index);
	},

	refreshDelete: function(enabled) {
		this.getElement('delete').prop('disabled', !enabled);
	},

	refreshNumber: function() {
		const index = this.getIndex();
		this.updateNumber(index);
	},

	updateNumber: function(index) {
		this.getElement('number').html('&numero;' + (index + 1));
	},

	getBasket: function() {
		return this.getChildField('ITEMS');
	},

	getCollection: function() {
		return this.getParentField();
	},

	getElement: function(key, context, method) {
		if (key === 'child') {
			const keySelector = this.getElementSelector(key);
			const selfSelector = this.getElementSelector('self');

			return (context || this.$el).nextUntil(selfSelector, keySelector);
		}

		return this.callParent('getElement', [key, context, method], constructor);
	},

}, {
	dataName: 'orderViewBox',
	pluginName: 'YandexMarket.OrderView.Box',
});

export const Box = constructor;