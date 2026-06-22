(function(BX, $, window) {

	const FieldRaw = BX.namespace('YandexMarket.Field.Raw');
	const FieldReference = BX.namespace('YandexMarket.Field.Reference');

	const constructor = FieldRaw.Collection = FieldReference.Collection.extend({

		defaults: {
			itemElement: '.js-input-collection__item',
			itemDeleteElement: '.js-input-collection__delete',
			addButtonElement: '.js-input-collection__add',
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
			this.handleAddButtonClick(true);
			this.handleItemDeleteClick(true);
		},

		unbind: function() {
			this.handleAddButtonClick(false);
			this.handleItemDeleteClick(false);
		},

		handleAddButtonClick: function(dir) {
			const addButtonSelector = this.getElementSelector('addButton');

			this.$el[dir ? 'on' : 'off']('click', addButtonSelector, $.proxy(this.onAddButtonClick, this));
		},

		handleItemDeleteClick: function(dir) {
			const itemDeleteSelector = this.getElementSelector('itemDelete');

			this.$el[dir ? 'on' : 'off']('click', itemDeleteSelector, $.proxy(this.onItemDeleteClick, this));
		},

		onAddButtonClick: function(evt) {
			const instance = this.addItem();

			instance.initEdit();

			evt.preventDefault();
		},

		onItemDeleteClick: function(evt) {
			const deleteButton = $(evt.target);
			const item = this.getElement('item', deleteButton, 'closest');

			this.deleteItem(item);

			evt.preventDefault();
		},

		getItemPlugin: function() { 
			return FieldRaw.Item;
		},

		addItem: function(source, context, method, isCopy) {
			const result = this.callParent('addItem', [source, context, method, isCopy], constructor);

			this.reflowAddButton();

			return result;
		},

		deleteItem: function(item, silent) {
			this.callParent('deleteItem', [item, silent], constructor);
			this.reflowAddButton();
		},

		reflowAddButton: function() {
			const addButton = this.getElement('addButton');
			let first = true;

			for (let index = addButton.length - 1; index >= 0; --index) {
				if (first) {
					addButton.eq(index).removeClass('is--hidden');
				} else {
					addButton.eq(index).addClass('is--hidden');
				}

				first = false;
			}
		}

	}, {
		dataName: 'FieldRawCollection',
	});

})(BX, jQuery, window);