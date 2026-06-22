(function(BX, $) {

	const Plugin = BX.namespace('YandexMarket.Plugin');
	const Input = BX.namespace('YandexMarket.Ui.Input');

	const constructor = Input.TagInput = Plugin.Base.extend({

		defaults: {
			width: 200,
			tags: true,
			dataAdapter: null,
			data: null,
			ajax: null,
			lazy: false,

			lang: {},
			langPrefix: 'YANDEX_MARKET_CHOSEN_'
		},

		initVars: function() {
			this.callParent('initVars', constructor);
			this._isPluginReady = false;
		},

		initialize: function() {
			this.clearClone();
			this.callParent('initialize', constructor);

			if (this.options.lazy) {
				this.bindLazy();
			} else {
				this.createPlugin();
			}
		},

		destroy: function() {
			this.destroyPlugin();
			this.unbindLazy();
			this.callParent('destroy', constructor);
		},

		bindLazy: function() {
			this.handleFocus(true);
			this.handleMouseDown(true);
		},

		unbindLazy: function() {
			this.handleFocus(false);
			this.handleMouseDown(false);
		},

		handleFocus: function(dir) {
			this.$el[dir ? 'on' : 'off']('focus', $.proxy(this.onFocus, this));
		},

		handleMouseDown: function(dir) {
			this.$el[dir ? 'on' : 'off']('mousedown', $.proxy(this.onMouseDown, this));
		},

		onFocus: function() {
			this.unbindLazy();
			setTimeout(() => this.createPlugin());
		},

		onMouseDown: function(evt) {
			this.unbindLazy();

			setTimeout(() => {
				this.createPlugin();
				this.openDropdown();
			});

			evt.preventDefault();
		},

		clearClone: function() {
			const pluginContainer = this.$el.next();

			if (this.$el.hasClass('select2-hidden-accessible')) {
				this.$el
					.removeClass('select2-hidden-accessible')
					.removeAttr('data-select2-id')
					.removeAttr('aria-hidden')
					.removeAttr('tabindex');

				this.$el.find('optgroup')
					.removeAttr('data-select2-id');

				this.$el.find('option')
					.removeAttr('data-select2-id');
			}

			if (pluginContainer.hasClass('select2')) {
				pluginContainer.remove();
			}
		},

		refreshPlugin: function() {
			this.destroyPlugin();
			this.createPlugin();
		},

		openDropdown: function() {
			this.$el.select2('open');
		},

		createPlugin: function() {
			if (this._isPluginReady) { return; }

			this._isPluginReady = true;
			this.$el.select2(this.createPluginOptions());
		},

		createPluginOptions: function() {
			return $.extend(true, {
				width: this.options.width,
				tags: this.options.tags,
				dataAdapter: this.options.dataAdapter,
				data: this.options.data,
				ajax: this.options.ajax,
				dropdownParent: this.$el.parent(),
			}, this.getLanguageOptions());
		},

		getLanguageOptions: function() {
			// noinspection JSUnusedGlobalSymbols
			return {
				placeholder: this.getLang('PLACEHOLDER'),
				language: {
					errorLoading: () => {
						return this.getLang('LOAD_ERROR');
					},
					inputTooLong: (t) => {
						return this.getLang('TOO_LONG', {
							'LIMIT': t.maximum
						});
					},
					inputTooShort: (t) => {
						// noinspection JSUnresolvedReference
						return this.getLang('TOO_SHORT', {
							'LIMIT': t.minimum
						});
					},
					loadingMore: () => {
						return this.getLang('LOAD_PROGRESS');
					},
					maximumSelected: (t) => {
						return this.getLang('MAX_SELECT', {
							'LIMIT': t.maximum
						});
					},
					noResults: () => {
						return this.getLang('NO_RESULTS');
					},
					searching: () => {
						return this.getLang('SEARCHING');
					}
				}
			};
		},

		destroyPlugin: function() {
			if (!this._isPluginReady) { return; }

			this._isPluginReady = false;
			this.$el.select2('destroy');
		}

	}, {
		dataName: 'uiTagInput'
	});

})(BX, jQuery);