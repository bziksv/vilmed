(function(BX) {

	const Reference = BX.namespace('YandexMarket.Field.Reference');
	const SkuField = BX.namespace('YandexMarket.Field.SkuField');

	const constructor = SkuField.Row = Reference.Base.extend({

		defaults: {
			inputElement: '.js-sku-field-row__input',
			deleteElement: '.js-sku-field-row__delete'
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
			this.handleIblockChange(true);
		},

		unbind: function() {
			this.handleDeleteClick(false);
			this.handleIblockChange(false);
		},

		handleDeleteClick: function(dir) {
			const deleteButton = this.getElement('delete');

			deleteButton[dir ? 'on' : 'off']('click', $.proxy(this.onDeleteClick, this));
		},

		handleIblockChange: function(dir) {
			const iblockInput = this.getInput('IBLOCK');

			iblockInput[dir ? 'on' : 'off']('change', $.proxy(this.onIblockChange, this));
		},

		onDeleteClick: function() {
			const parentField = this.getParentField();

			if (parentField != null) {
				parentField.deleteItem(this.$el);
			}
		},

		onIblockChange: function(evt) {
			const iblockId = evt.target.value;

			this.reloadFieldEnum(iblockId);
		},

		preselect: function() {
			this.getInput('FIELD')
				.find('option')
				.filter((index, option) => option.value === 'ID')
				.prop('selected', true);
		},

		clear: function() {
			const iblockInput = this.getInput('IBLOCK');

			this.callParent('clear');

			if (iblockInput != null) {
				this.reloadFieldEnum(iblockInput.value);
			}
		},

		reloadFieldEnum: function(iblockId) {
			const fieldSource = this.getSource();

			if (fieldSource.hasEnum(iblockId)) {
				const fieldEnum = fieldSource.getEnum(iblockId);
				this.renderFieldEnum(fieldEnum);
			} else {
				fieldSource.loadEnum(iblockId, $.proxy(this.renderFieldEnum, this));
			}
		},

		renderFieldEnum: function(fieldEnum) {
			const fieldInput = this.getInput('FIELD');
			const previousValue = fieldInput.val();
			const noValueOption = this.getNoValueOption(fieldInput);

			fieldInput.empty();

			if (noValueOption != null) {
				fieldInput.append(noValueOption);
			}

			for (let i = 0; i < fieldEnum.length; i++) {
				const data = fieldEnum[i];
				const option = document.createElement('option');
				option.value = data.ID;
				// noinspection JSUnresolvedReference
				option.innerText = data.VALUE;
				// noinspection JSUnresolvedReference
				option.selected = (previousValue === data.VALUE);

				fieldInput.append(option);
			}
		},

		getNoValueOption: function(select) {
			const option = select.children().get(0);

			if (option && (option.tagName || '').toLowerCase() === 'option' && option.value === '') {
				return option;
			}

			return null;
		},

		getSource: function() {
			const parentField = this.getParentField() || this;
			const parentElement = parentField.$el;

			return SkuField.Source.getInstance(parentElement);
		}

	}, {
		dataName: 'FieldSkuFieldRow',
		pluginName: 'YandexMarket.Field.SkuField.Row'
	});

})(BX);