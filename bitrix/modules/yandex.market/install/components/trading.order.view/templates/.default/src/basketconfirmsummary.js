import { BasketConfirmForm } from "./basketconfirmform";

const BX = window.BX;
const $ = window.jQuery;
const FieldReference = BX.namespace('YandexMarket.Field.Reference');

const constructor = FieldReference.Summary.extend({

	defaults: {
		modalElement: '.js-yamarket-basket-confirm-summary__modal',
		fieldElement: '.js-yamarket-basket-confirm-summary__field',

		modalWidth: 500,
		modalHeight: 350,

		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_CONFIRM_',
		lang: {},
	},

	initVars: function() {
		this.callParent('initVars', constructor);
		this._confirmDeferred = null;
	},

	onEditModalSave: function() {
		this.callParent('onEditModalSave', constructor);
		this.allowRemove(true);
		this.resolveConfirm();
	},

	onEditModalClose: function() {
		this.callParent('onEditModalClose', constructor);
		this.allowRemove(false);
		this.rejectConfirm();
	},

	validate: function() {
		// nothing
	},

	confirm: function(boxCollection) {
		const changes = boxCollection.getCountChanges();

		if (changes.length === 0) {
			this.allowRemove(false);
			return;
		}

		this.setItemsChanges(changes);
		this.initEdit();

		return this.waitConfirm();
	},

	allowRemove: function(allow) {
		this.callField('allowRemove', [allow]);
	},

	setItemsChanges: function(changes) {
		this.callField('setItemsChanges', [changes]);
	},

	waitConfirm: function() {
		this._confirmDeferred = new $.Deferred();

		return this._confirmDeferred;
	},

	resolveConfirm: function() {
		const deferred = this._confirmDeferred;

		if (deferred === null) { return; }

		this._confirmDeferred = null;
		deferred.resolve();
	},

	rejectConfirm: function() {
		const deferred = this._confirmDeferred;

		if (deferred === null) { return; }

		this._confirmDeferred = null;
		deferred.reject();
	},

	getFieldPlugin: function() {
		return BasketConfirmForm;
	},

	getLang: function(key, replaces) {
		if (key === 'MODAL_TITLE' && this.callField('getInput', ['REASON']) != null) {
			key = 'MODAL_TITLE_WITH_REASON';
		}

		return this.callParent('getLang', [key, replaces], constructor);
	},

}, {
	dataName: 'orderViewBasketConfirmSummary',
	pluginName: 'YandexMarket.OrderView.BasketConfirmSummary',
});

export const BasketConfirmSummary = constructor;