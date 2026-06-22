(function(BX) {

	const Plugin = BX.namespace('YandexMarket.Plugin');
	const SkuField = BX.namespace('YandexMarket.Field.SkuField');

	const constructor = SkuField.Source = Plugin.Base.extend({

		defaults: {
			url: null,
		},

		initVars: function() {
			this.callParent('initVars', constructor);
			this._enumCache = {};
		},

		setUrl: function(url) {
			this.options.url = url;
		},

		addEnum: function(iblockId, fields) {
			iblockId = this.normalizeIblockId(iblockId);

			this._enumCache[iblockId] = fields;
		},

		hasEnum: function(iblockId) {
			iblockId = this.normalizeIblockId(iblockId);

			return this._enumCache.hasOwnProperty(iblockId);
		},

		getEnum: function(iblockId) {
			iblockId = this.normalizeIblockId(iblockId);

			return this._enumCache[iblockId];
		},

		loadEnum: function(iblockId, successCallback, failCallback) {
			iblockId = this.normalizeIblockId(iblockId);

			if (iblockId > 0) {
				BX.ajax({
					url: this.options.url,
					method: 'POST',
					data: {
						IBLOCK_ID: iblockId
					},
					dataType: 'json',
					onsuccess: $.proxy(this.loadEnumEnd, this, iblockId, successCallback),
					onfailure: failCallback
				});
			} else {
				failCallback && failCallback();
			}
		},

		loadEnumEnd: function(iblockId, callback, data) {
			if (data.status === 'ok') {
				this.addEnum(iblockId, data.enum);
				callback && callback(data.enum);
			}
		},

		normalizeIblockId: function(iblockId) {
			return Math.max(0, parseInt(iblockId) || 0);
		}

	}, {
		dataName: 'FieldSkuSource',
		pluginName: 'YandexMarket.Field.SkuField.Source'
	});

})(BX);