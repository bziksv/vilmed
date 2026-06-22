import { TagSettingRegistry } from "./tagsettingregistry";

const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');

export class TagSetting extends Reference.Complex {

	static dataName = 'FieldParamSetting';
	static defaults = Object.assign({}, Reference.Complex.prototype.defaults, {
		inputElement: '.js-param-tag-setting__input',
		childElement: '.js-param-tag-setting__child',

		group: null,
		proxy: true,
		name: 'SETTING',

		lang: {},
		langPrefix: 'YANDEX_MARKET_FIELD_PARAM_'
	});

	_syncGroupTimeout: ?number;
	_groupState: ?string;
	_enabled: boolean;

	initialize() : void {
		super.initialize();

		if (this.el.hasAttribute('data-disabled')) {
			this._enabled = false;
			this.el.removeAttribute('data-disabled');
		} else {
			this._enabled = true;
		}

		this.pushRegistry();
	}

	destroy() : void {
		this.shiftRegistry();
		super.destroy();
	}

	handleMainChange(dir: boolean) : void {
		this.$el[dir ? 'on' : 'off']('change', $.proxy(this.onMainChange, this));
	}

	onMainChange() : void {
		this.syncGroupDelayed();
	}

	clear() : void {
		if (this._groupState === TagSettingRegistry.STATE_SHADOW) { return; }

		super.clear();
	}

	pushRegistry() : void {
		if (this.options.group == null) { return; }

		TagSettingRegistry.push(this.options.group, this);
	}

	shiftRegistry() : void {
		if (this.options.group == null) { return; }

		TagSettingRegistry.shift(this.options.group, this);
	}

	groupMain() : void {
		this._groupState = TagSettingRegistry.STATE_MAIN;
		this.$el.removeClass('is--shadow');
		this.handleMainChange(true);
	}

	destroyGroupMain() : void {
		this._groupState = null;
		clearTimeout(this._syncGroupTimeout);
		this.handleMainChange(false);
	}

	groupShadow() : void {
		this._groupState = TagSettingRegistry.STATE_SHADOW;
		this.$el.addClass('is--shadow');
	}

	syncGroupDelayed() : void {
		clearTimeout(this._syncGroupTimeout);
		this._syncGroupTimeout = setTimeout(() => this.syncGroup(), 100);
	}

	syncGroup() : void {
		TagSettingRegistry.sync(this.options.group, this);
	}

	enabled() : boolean {
		return this._enabled;
	}

	enable() : void {
		if (this.options.group == null) { return; }

		this._enabled = true;
		TagSettingRegistry.enable(this.options.group, this);
	}

	disable() : void {
		if (this.options.group == null) { return; }

		this._enabled = false;
		TagSettingRegistry.disable(this.options.group, this);
	}

}