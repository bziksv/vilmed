(function() {

	const BX = window.BX;
	const Ui = BX.namespace('YandexMarket.Ui');

	Ui.AssetsLoader = {

		delay: 0,

		callback: function(assets) {
			return (callback) => {
				this.load(assets).then(() => { callback() });
			};
		},

		load: function(assets) {
			const resources = this.resources(assets);

			return Promise.all([
				this.loadJs(resources['js']),
				this.loadCss(resources['css'])
			]);
		},

		loadJs: function(js) {
			let promise = Promise.resolve();

			for (const url of js) {
				promise = promise.then(() => new Promise((resolve) => {
					BX.loadScript(url, () => {
						if (this.delay > 0) {
							setTimeout(resolve, this.delay);
							return;
						}

						resolve();
					});
				}));
			}

			return promise;
		},

		loadCss: function(css) {
			if (css.length === 0) { return Promise.resolve(); }

			return new Promise((resolve) => {
				BX.loadCSS(css);
				setTimeout(resolve, 100);
			});
		},

		resources: function(assets) {
			const resources = {
				js: [],
				css: [],
			};

			if (this.isDefined(assets['variable'])) { return resources; }

			for (const type of Object.keys(resources)) {
				if (!assets[type]) { continue; }

				if (!Array.isArray(assets[type])) {
					resources[type].push(assets[type]);
					continue;
				}

				resources[type] = assets[type];
			}

			if (Array.isArray(assets['rel'])) {
				for (const rel of assets['rel']) {
					const relResources = this.resources(rel);

					for (const type of Object.keys(resources)) {
						if (relResources[type].length === 0) { continue; }

						resources[type] = relResources[type].concat(resources[type]);
					}
				}
			}

			return resources;
		},

		isDefined: function(name) {
			if (name == null) { return false; }

			let level = window;

			for (const part of name.split('.')) {
				if (level[part] == null) { return false; }

				level = level[part];
			}

			return true;
		}

	};

})();