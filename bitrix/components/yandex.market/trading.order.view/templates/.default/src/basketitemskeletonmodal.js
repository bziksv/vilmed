const BX = window.BX;
const $ = window.jQuery;
const YandexMarket = BX.namespace('YandexMarket');
const Plugin = BX.namespace('YandexMarket.Plugin');

const constructor = Plugin.Base.extend({

	defaults: {
		modalWidth: 400,
		modalHeight: 150,

		lang: {},
	},

	initVars: function() {
		this.callParent('initVars', constructor);
		this._modal = null;
		this._openDeferred = null;
	},

	handleModal: function(modal, dir) {
		this.handleModalSave(modal, dir);
		this.handleModalClose(modal, dir);
	},

	handleModalSave: function(modal, dir) {
		BX[dir ? 'addCustomEvent' : 'removeCustomEvent'](modal, 'onWindowSave', BX.proxy(this.onModalSave, this));
	},

	handleModalClose: function(modal, dir) {
		BX[dir ? 'addCustomEvent' : 'removeCustomEvent'](modal, 'onWindowClose', BX.proxy(this.onModalClose, this));
	},

	onModalSave: function() {
		const modal = this.modal();

		this.resolve(modal);
		this.handleModal(modal, false);
		this.destroyModal(modal);
		modal.Close();
	},

	onModalClose: function() {
		const modal = this.modal(true);

		if (modal == null) { return; }

		this.reject();
		this.handleModal(modal, false);
		this.destroyModal(modal);
	},

	open: function() {
		const modal = this.modal();

		modal.SetContent(this.$el.html());
		modal.Show();

		this.afterOpen(modal);
		this.handleModal(modal, true);

		return (this._openDeferred = new $.Deferred());
	},

	afterOpen: function(modal) {},

	resolve: function(modal) {
		if (this._openDeferred == null) { return; }

		this._openDeferred.resolve(this.resolveValues(modal));
		this._openDeferred = null;
	},

	reject: function() {
		if (this._openDeferred == null) { return; }

		this._openDeferred.reject();
		this._openDeferred = null;
	},

	resolveValues: function(modal) {
		return null;
	},

	modal: function(skipCreate) {
		if (this._modal == null && !skipCreate) {
			this._modal = new YandexMarket.EditDialog({
				'title': this.getLang('TITLE'),
				'draggable': true,
				'resizable': true,
				'buttons': [YandexMarket.EditDialog.btnSave, YandexMarket.EditDialog.btnCancel],
				'width': this.options.modalWidth,
				'height': this.options.modalHeight,
			});
		}

		return this._modal;
	},

	modalContent: function(modal) {
		return $(modal.PARTS.CONTENT_DATA);
	},

	destroyModal: function() {
		this._modal = null;
	},

});

export const BasketItemSkeletonModal = constructor;