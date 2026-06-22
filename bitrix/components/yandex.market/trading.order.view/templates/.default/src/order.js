const BX = window.BX;
const $ = window.jQuery;
const FieldReference = BX.namespace('YandexMarket.Field.Reference');

const constructor = FieldReference.Complex.extend({

	defaults: {
		childElement: '.js-yamarket-order__field',
		inputElement: '.js-yamarket-order__input',
		areaElement: '.js-yamarket-order__area',
		refreshUrl: null,
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
		this.handleActivityEnd(true);
	},

	unbind: function() {
		this.handleActivityEnd(false);
	},

	handleActivityEnd: function(dir) {
		BX[dir ? 'addCustomEvent' : 'removeCustomEvent'](this.el, 'yamarketActivitySubmitEnd', BX.proxy(this.onActivityEnd, this));
	},

	onActivityEnd: function() {
		this.refresh();
	},

	getId: function() {
		const input = this.getInput('ID');

		return input && input.val();
	},

	confirm: function() {
		const boxCollection = this.getChildField('BOX');
		const basketConfirm = this.getChildField('BASKET_CONFIRM');

		if (!boxCollection || !basketConfirm) { return; }

		return basketConfirm.confirm(boxCollection);
	},

	validate: function() {
		this.callChildList('validate');
	},

	commit: function() {
		const boxCollection = this.getChildField('BOX');
		let hasChanges = false;
		let boxIndex = 0;

		if (boxCollection == null) { return; }

		boxCollection.callItemList(function(box) {
			if (box.getBasket().commit(boxIndex)) {
				hasChanges = true;
			}

			++boxIndex;
		});

		hasChanges && this.refresh();
	},

	refresh: function() {
		$.ajax({ url: this.options.refreshUrl })
			.then($.proxy(this.updateArea, this))
			.then($.proxy(this.hideLoading, this));
	},

	updateArea: function(html) {
		const contents = $(html);
		const newAreas = this.getElement('area', contents);
		const newMap = this.mapAreas(newAreas);
		const existsAreas = this.getElement('area');
		const existsMap = this.mapAreas(existsAreas);

		for (const type in newMap) {
			if (!newMap.hasOwnProperty(type)) { continue; }
			if (!existsMap.hasOwnProperty(type)) { continue; }

			const newArea = newMap[type];
			const existsArea = existsMap[type];

			existsArea.replaceWith(newArea);

			BX.onCustomEvent(newArea[0], 'onYaMarketContentUpdate', [
				{ target: newArea[0] }
			]);
		}
	},

	mapAreas: function(areas) {
		const result = {};

		for (let index = 0; index < areas.length; ++index) {
			const area = areas.eq(index);
			const type = area.data('type');

			if (!type) { continue; }

			result[type] = area;
		}

		return result;
	},

	hideLoading: function() {
		BX.closeWait(this.el);
	}


}, {
	dataName: 'orderViewOrder',
	pluginName: 'YandexMarket.OrderView.Order',
});

export const Order = constructor;