const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');

export class Tag extends Reference.Complex {
	
	static dataName = 'FieldParamTag';
	static defaults = Object.assign({}, Reference.Complex.prototype.defaults, {
		inputElement: '.js-param-tag__input',
		childElement: '.js-param-tag__child',

		lang: {},
		langPrefix: 'YANDEX_MARKET_FIELD_PARAM_'
	});

	preselect() : void {
		this.getChildField('PARAM_VALUE').preselect();
	}

	enable() : void {
		const settings = this.getChildField('SETTINGS');

		if (settings == null) { return; }

		settings.enable();
	}

	disable() : void {
		const settings = this.getChildField('SETTINGS');

		if (settings == null) { return; }

		settings.disable();
	}

}