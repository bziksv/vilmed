const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');

export class TagSettings extends Reference.Complex {

	static dataName = 'FieldParamTagSettings';
	static defaults = Object.assign({}, Reference.Complex.prototype.defaults, {
		inputElement: '.js-param-tag-settings__input', // not used
		childElement: '.js-param-tag-settings__child',

		lang: {},
		langPrefix: 'YANDEX_MARKET_FIELD_PARAM_'
	});

	enable() : void {
		this.callChildList('enable');
	}

	disable() : void {
		this.callChildList('disable');
	}

}