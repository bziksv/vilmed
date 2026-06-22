const $ = window.YMarketJQuery || window.jQuery;
const BX = window.BX;
const Plugin = BX.namespace('YandexMarket.Plugin');
const Utils = BX.namespace('YandexMarket.Utils');

// noinspection JSUnusedGlobalSymbols
export class AjaxReloader extends Plugin.Base {

	static dataName = 'uiFormAjaxReload';
	static defaults = Object.assign({}, Plugin.Base.prototype.defaults, {
		listenElement: '.js-ajax-reloader__field',
		controlCellElement: '.adm-detail-content-cell-r',
		errorElement: '.js-ajax-reloader__error',
		errorTemplate: `<p class="js-ajax-reloader__error" style="color: red;"><strong>#TITLE#</strong><br /> #TEXT#</p>`,

		reloadMap: null,
		reloadDelay: 5000,

		lang: {},
		langPrefix: 'YANDEX_MARKET_UI_FORM_AJAX_RELOADER_'
	});

	// noinspection JSUnusedGlobalSymbols
	initVars() : void {
		this._targets = [];
		this._listenValues = {};
		this._reloadTimeout = null;
		this._reloadQuery = null;
	}

	initialize() : void {
		this.resetValues();
		this.bind();
	}

	destroy() : void {
		this.unbind();
		super.destroy();
	}

	bind() : void {
		this.handleChange(true);
	}

	unbind() : void {
		this.handleChange(false);
	}

	handleChange(dir: boolean) : void {
		this.getElement('listen')[dir ? 'on' : 'off']('change', $.proxy(this.onChange, this));
	}

	onChange(evt: Event) : void {
		const field = $(evt.currentTarget);
		const name = field.data('reloader-name');
		const value = this.fieldValue(field);

		if (value === this._listenValues[name]) { return; }

		const targets = field.data('reloader-target').split(',').map((part) => part.trim());

		this._listenValues[name] = value;
		this.pushTargets(targets);
		this.reloadDelayed();
	}

	resetValues() : void {
		const fields = this.getElement('listen');

		for (let i = fields.length - 1; i >= 0; --i) {
			const field = fields.eq(i);
			const name = field.data('reloader-name');

			this._listenValues[name] = this.fieldValue(field);
		}
	}

	fieldValue(field: JQuery) : string {
		const plugin = this.fieldPlugin(field);
		const significant = field.data('reloaderSignificant');
		let value;

		if (plugin != null) {
			// noinspection JSUnresolvedReference
			value = plugin.getValue();
			value = this.pluginSignificant(value, significant);
		} else {
			value = this.rawValue(field);
			value = this.rawSignificant(value, significant);
		}

		return this.stringifyValue(value);
	}

	fieldPlugin(field: JQuery) : Plugin.Base {
		const control = this.getElement('controlCell', field).children().eq(0);
		const plugin = Plugin.manager.getInstance(control);

		if (plugin == null || typeof plugin.getValue !== 'function') { return null; }

		return plugin;
	}

	pluginSignificant(value, significant: string) {
		if (significant == null || value == null) { return value; }

		if (Array.isArray(value)) {
			return value.map((row) => this.pluginSignificant(row, significant));
		}

		if (typeof value !== 'object') { return value; }

		const filtered = {};

		for (const key of significant.split(', ')) {
			if (value[key] == null) { continue; }

			filtered[key] = value[key];
		}

		return filtered;
	}

	rawValue(field: JQuery) : Array {
		return field.find('input, textarea, select').serializeArray();
	}

	rawSignificant(value: Array, significant: string) : Array {
		if (significant == null) { return value; }

		const significantKeys = significant.split(', ');
		const filtered = [];

		for (const fieldValue of value) {
			if (fieldValue.name == null) { continue; }

			for (const significantKey of significantKeys) {
				if (fieldValue.name.indexOf(significantKey) === 0) {
					filtered.push(fieldValue);
					break;
				}
			}
		}

		return filtered;
	}

	stringifyValue(value) : string {
		 if (!Array.isArray(value)) {
			 return JSON.stringify(value);
		 }

		 const unique = [];

		 for (const row of value) {
			 const valueEncoded = this.stringifyValue(row);

			 if (unique.indexOf(valueEncoded) === -1) {
				 unique.push(valueEncoded);
			 }
		 }

		 return unique.join(', ');
	}

	pushTargets(targets: string[]) : void {
		for (const target of targets) {
			if (this._targets.indexOf(target) !== -1) { continue; }

			this._targets.push(target);
		}
	}

	reloadDelayed() : void {
		this.reloadCancel();
		this._reloadTimeout = setTimeout(
			() => { this.reload(); },
			this.options.reloadDelay
		);
	}

	reload() : void {
		this.reloadCancel();
		this.resetValues();

		this._reloadQuery = $.ajax({
			url: this.$el.attr('action'),
			method: 'POST',
			data: this.reloadData(),
			dataType: 'json',
		});

		this._reloadQuery.then(
			$.proxy(this.reloadEnd, this),
			$.proxy(this.reloadStop, this)
		);
	}

	reloadData() : Array {
		const form = this.$el.serializeArray();
		const common = [
			{ name: 'ajaxReloader', value: 'Y' },
			{ name: 'ajaxReloaderTarget', value: this._targets.join(', ') }
		];

		return common.concat(form);
	}

	reloadEnd(data: Object) : void {
		for (const target of this._targets) {
			if (data[target] != null) {
				this.updateTarget(target, data[target]);
			} else {
				this.errorTarget(target, 'missing data');
			}
		}

		this._targets = [];
	}

	reloadStop(xhr: JQueryXHR, textStatus: ?string, error: ?string) : void {
		if (textStatus === 'abort') { return; }

		for (const target of this._targets) {
			this.errorTarget(target, error || textStatus);
		}
	}

	reloadCancel() : void {
		if (this._reloadTimeout != null) {
			clearTimeout(this._reloadTimeout);
			this._reloadTimeout = null;
		}

		if (this._reloadQuery != null) {
			this._reloadQuery.abort();
			this._reloadQuery = null;
		}
	}

	updateTarget(target: string, content: string) : void {
		try {
			// noinspection JSUnresolvedReference
			const parsedContent = BX.processHTML(content, true);
			const newElement = $(parsedContent.HTML);
			const oldElement = this.targetElement(target);

			newElement.attr('style', oldElement.attr('style')); // copy tab styles
			Plugin.manager.destroyContext(oldElement);
			oldElement.replaceWith(newElement);

			// noinspection JSUnresolvedReference
			BX.ajax.processScripts(parsedContent.SCRIPT);
			Plugin.manager.initializeContext(newElement);
		} catch (error) {
			console.error(error);
			this.errorTarget(target, error.message);
		}
	}

	errorTarget(target: string, message: string) : void {
		const targetElement = this.targetElement(target);
		const existError = this.getElement('error', targetElement);
		const newError = Utils.compileTemplate(this.options.errorTemplate, {
			'TITLE': this.getLang('ERROR_TITLE'),
			'TEXT': message,
		});

		if (existError.length > 0) {
			existError.replaceWith(newError);
			return;
		}

		const targetBody = targetElement.find('.b-form-section').not('.fill--primary');

		if (targetBody.length > 0) {
			targetBody.eq(0).before(newError);
			return;
		}

		targetElement.prepend(newError);
	}

	targetElement(target: string) : JQuery {
		return $('#' + target);
	}
}
