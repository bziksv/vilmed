(function(BX) {

	const Plugin = BX.namespace('YandexMarket.Plugin');
	const Reference = BX.namespace('YandexMarket.Field.Reference');

	const constructor = Reference.Complex = Reference.Base.extend({

		defaults: {
			childElement: null
		},

		initialize: function() {
			this.callParent('initialize', constructor);
			this.setParentForChild();
			this.setBaseNameChild();
		},

		destroy: function() {
			this.callChildList('destroy');
			this.callParent('destroy', constructor);
		},

		cloneInstance: function(newInstance) {
			const baseName = this.getBaseName();
			const index = this.getIndex();
			const newChildInstanceMap = newInstance.getChildInstanceMap();
			const rawValues = this.getRawValue();

			newInstance.setBaseName(baseName);
			newInstance.setIndex(index);
			newInstance.setRawValue(rawValues);

			this.callChildList(function(childInstance) {
				const childName = childInstance.getName();
				const newChildInstance = newChildInstanceMap[childName];

				childInstance.cloneInstance(newChildInstance);
				newChildInstance.setParentField(newInstance);
			});
		},

		initEdit: function() {
			let result = this.callParent('initEdit', constructor);

			if (!result) {
				this.callChildList(function(instance) {
					if (!result) {
						result = instance.initEdit();
					}
				});
			}

			return result;
		},

		setParentForChild: function() {
			const parent = this;

			this.callChildList(function(instance) {
				instance.setParentField(parent);
			});
		},

		setBaseName: function(baseName) {
			this.callParent('setBaseName', [baseName], constructor);
			this.setBaseNameChild();
		},

		setBaseNameChild: function() {
			const baseName = this.getBaseName();

			this.callChildList(function(instance) {
				if (instance.isProxy()) {
					instance.setBaseName(baseName);
					return;
				}

				const name = instance.getName();
				const childName = baseName + (name.indexOf('[') !== -1 ? name : '[' + name + ']');

				instance.setBaseName(childName);
			});
		},

		clear: function() {
			this.callParent('clear', constructor);
			this.callChildList('clear');
		},

		updateName: function() {
			this.callParent('updateName', constructor);
			this.callChildList('updateName');
		},

		unsetName: function() {
			this.callParent('unsetName', constructor);
			this.callChildList('unsetName');
		},

		setValue: function(valueList) {
			this.setRawValue(valueList);
			this.callChildList(function(instance) {
				instance.setValue(valueList[instance.getName()]);
			});
		},

		setRawValue: function(valueList) {
			this.callParent('setValue', [valueList], constructor);
		},

		applyDefaults: function() {
			this.callParent('applyDefaults', constructor);
			this.callChildList('applyDefaults');
		},

		getDefaultValues: function() {
			const result = this.callParent('getDefaultValues', constructor);

			this.callChildList(function(instance) {
				result[instance.getName()] = instance.getDefaultValues();
			});

			return result;
		},

		getValue: function() {
			const result = this.getRawValue();

			this.callChildList(function(instance) {
				result[instance.getName()] = instance.getValue();
			});

			return result;
		},

		getRawValue: function() {
			return this.callParent('getValue', constructor);
		},

		getDisplayValue: function() {
			const result = this.callParent('getDisplayValue', constructor);

			this.callChildList(function(instance) {
				result[instance.getName()] = instance.getDisplayValue();
			});

			return result;
		},

		callChildList: function(method, args) {
			const childList = this.getElement('child');

			for (let childIndex = 0; childIndex < childList.length; childIndex++) {
				const child = childList.eq(childIndex);

				if (!child.hasClass('is--hidden')) { // is not placeholder
					this.callChild(child, method, args);
				}
			}
		},

		callChild: function(element, method, args) {
			const instance = this.getChildInstance(element);

			if (typeof method === 'string') {
				instance[method].apply(instance, args);
			} else {
				method(instance);
			}
		},

		getChildInstanceMap: function() {
			const result = {};

			this.callChildList(function(instance) {
				result[instance.getName()] = instance;
			});

			return result;
		},

		getChildInstance: function(element) {
			const pluginName = element.data('plugin');
			const plugin = Plugin.manager.getPlugin(pluginName);

			return plugin.getInstance(element);
		},

		getChildField: function(fieldName) {
			const map = this.getChildInstanceMap();

			return fieldName in map ? map[fieldName] : null;
		}

	});

})(BX);
