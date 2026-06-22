const BX = window.BX;
const $ = window.jQuery;
const YandexMarket = BX.namespace('YandexMarket');
const Plugin = BX.namespace('YandexMarket.Plugin');

const constructor = Plugin.Base.extend({

	defaults: {
		url: null,
		width: 400,
		height: 300,
		items: [],
	},

	initialize: function() {
		this.callParent('initialize', constructor);
		this.bind();
	},

	destroy: function() {
		this.unbind();
		this.callParent('initialize', constructor);
	},

	bind: function() {
		this.handleClick(true);
		this.handleShipmentSubmit(true);
	},

	unbind: function() {
		this.handleClick(false);
		this.handleShipmentSubmit(false);
	},

	handleClick: function(dir) {
		this.$el[dir ? 'on' : 'off']('click', $.proxy(this.onClick, this));
	},

	handleShipmentSubmit: function(dir) {
		if (!dir || this.isDisabled()) {
			BX[dir ? 'addCustomEvent' : 'removeCustomEvent']('yamarketShipmentSubmitEnd', BX.proxy(this.onShipmentSubmitEnd, this));
		}
	},

	onShipmentSubmitEnd: function(status) {
		if (status === 'ok') {
			this.enable();
			this.handleShipmentSubmit(false);
		}
	},

	onClick: function() {
		this.createDropdown();
		this.handleClick(false);
	},

	isDisabled: function() {
		return this.$el.hasClass('is--hidden');
	},

	enable: function() {
		this.$el.removeClass('is--hidden');
	},

	createDropdown: function() {
		const items = this.getDropdownItems();

		BX.adminShowMenu(this.el, items);
	},

	getDropdownItems: function() {
		const result = [];
		const items = this.options.items;
		let itemIndex;
		let item;

		for (itemIndex = 0; itemIndex < items.length; itemIndex++) {
			item = items[itemIndex];

			result.push({
				TEXT: item.TITLE,
				ACTION: this.openDialog.bind(this, item.TYPE),
			});
		}

		return result;
	},

	openDialog: function(type) {
		const item = this.getItem(type);
		const url = this.buildUrl(item);
		const dialog = this.createDialog(url, item);

		dialog.Show();
	},

	createDialog: function(url, item) {
		return new YandexMarket.PrintDialog({
			title: item.DIALOG_TITLE || item.TITLE,
			content_url: url,
			width: item.WIDTH || this.options.width,
			height: item.HEIGHT || this.options.height,
			buttons: [
				YandexMarket.PrintDialog.btnSave,
				YandexMarket.PrintDialog.btnCancel
			]
		});
	},

	buildUrl: function(item) {
		let result = this.options.url;

		result +=
			(result.indexOf('?') === -1 ? '?' : '&')
			+ 'type=' + item.TYPE;

		return result;
	},

	getItem: function(type) {
		const items = this.options.items;
		let result;
		let itemIndex;
		let item;

		for (itemIndex = 0; itemIndex < items.length; itemIndex++) {
			item = items[itemIndex];

			if (item.TYPE === type) {
				result = item;
				break;
			}
		}

		return result;
	},

}, {
	dataName: 'orderViewShipmentPrint',
	pluginName: 'YandexMarket.OrderView.ShipmentPrint',
});

export const ShipmentPrint = constructor;