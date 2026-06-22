(function(BX) {

	const Reference = BX.namespace('YandexMarket.Field.Reference');
	const SkuField = BX.namespace('YandexMarket.Field.SkuField');

	const constructor = SkuField.Table = Reference.Collection.extend({

		defaults: {
			itemElement: '.js-sku-field-row',
			addElement: '.js-sku-field__add',

			persistent: true,
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
			this.handleAddClick(true);
		},

		unbind: function() {
			this.handleAddClick(false);
		},

		handleAddClick: function(dir) {
			this.getElement('add')[dir ? 'on' : 'off']('click', $.proxy(this.onAddClick, this));
		},

		onAddClick: function(evt) {
			this.addItem();
			evt.preventDefault();
		},

		addItem: function(source, context, method, isCopy) {
			const item = this.callParent('addItem', [source, context, method, isCopy], constructor);

			item && item.preselect();

			return item;
		},

		deleteItem: function(item, silent) {
			this.callParent('deleteItem', [item, silent], constructor);
			!silent && this.$el.trigger('change');
		},

		getValue: function() {
			const value = this.callParent('getValue', constructor);

			if (!Array.isArray(value)) { return []; }

			const filtered = [];

			for (const row of value) {
				if (
					row['IBLOCK'] > 0
					&& (row['FIELD'] !== '' && row['FIELD'] != null)
				) {
					filtered.push(row);
				}
			}

			return filtered;
		},

		getItemPlugin: function() {
			return SkuField.Row;
		}

	}, {
		dataName: 'FieldSkuFieldTable',
		pluginName: 'YandexMarket.Field.SkuField.Table'
	});

})(BX);