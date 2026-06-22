// noinspection JSUnresolvedVariable
import type {Transport} from "./Transport";
import {ParameterCollection} from "../Parameters/Dto/ParameterCollection";
import type {ParametersPool} from "../Parameters/ParametersPool";

const $ = window.YMarketJQuery ?? window.$;

export class Form {

	static defaults = {
		type: null,
		payload: {},
		fields: {},
		apiKeyField: null,
		parentValue: null,
	}

	constructor(form: ?HTMLFormElement, transport: Transport, parametersPool: ParametersPool, options: Object = {}) {
		this.form = form;
		this.transport = transport;
		this.parametersPool = parametersPool;
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	destroy() : void {
		this.form = null;
	}

	handleChange(dir: boolean, callback: () => {}): void {
		if (callback == null) { return; }

		for (const [, selector] of Object.entries(this.options.fields)) {
			if (Array.isArray(selector)) {
				this.handleEvent(dir, callback, selector[0], selector[1], selector[2]);
				continue;
			}

			this.handleEvent(dir, callback, selector);
		}
	}

	handleEvent(dir: boolean, callback: () => {}, selector: string, childSelector: ?string, eventName: ?string) : void {
		const element = this.form.querySelector(this.cssSelector(selector));

		if (element == null) { return; }

		if (eventName === 'onLookupInputChange') {
			// noinspection JSUnresolvedReference
			window.jsUtils[dir ? 'addCustomEvent' : 'removeCustomEvent']('onLookupInputChange', this.onLookupInputChange.bind(this, callback, element.id));
			return;
		}

		if (eventName == null) { eventName = 'change'; }

		if (childSelector != null) {
			$(element)[dir ? 'on' : 'off'](eventName, this.cssSelector(childSelector), callback);
			return;
		}

		element[dir ? 'addEventListener' : 'removeEventListener'](eventName, callback);
	}

	onLookupInputChange(callback: () => {}, layoutId: string, params: Object, data: Object) : void {
		// noinspection JSUnresolvedReference
		if (data.ACTION === 'add' && layoutId === `layout_${data.CONTROL_ID}`) {
			callback();
		}
	}

	reload(category: string) : Promise<Object> {
		if (this.options.parentValue != null) {
			const parentValue = this.options.parentValue() || {};

			return this.parametersPool.get(category || parentValue.category)
				.then((parametersCollection) => {
					return {
						parameters: parametersCollection,
						parentCategory: parentValue.category || '',
						parentParameters: parentValue.parameters || {},
					};
				});
		}

		return this.transport.fetch('reload', {
			category: category,
			form: this.transportPayload(),
		})
			.then((data: Object) : void => {
				data.parameters = new ParameterCollection(data.parameters || []);
				return data;
			});
	}

	transportPayload() : Object {
		return {
			type: this.options.type,
			payload: this.options.payload,
			fields: this.values(),
		};
	}

	values() {
		const result = {};

		for (const [key, elements] of Object.entries(this.fields())) {
			result[key] = this.value(elements);
		}

		return result;
	}

	value(input: HTMLInputElement|HTMLSelectElement) {
		if (!input.multiple) { return input.value; }

		return Array.from(input.querySelectorAll('option'))
			.filter((option) => option.selected)
			.map((option) => option.value);
	}

	fields() : Object<string, HTMLInputElement|HTMLSelectElement> {
		if (!this.options.fields) { return {}; }

		const result = {};

		for (const [name, selector] of Object.entries(this.options.fields)) {
			const input = this.field(selector);

			if (input == null) { continue; }

			result[name] = input;
		}

		return result;
	}

	field(selector: string|string[]|null) : ?HTMLInputElement|HTMLSelectElement {
		if (selector === null || this.form == null) { return null; }

		if (Array.isArray(selector)) {
			const container = this.form.querySelector(this.cssSelector(selector[0]));

			if (container == null) { return null; }

			return container.querySelector(this.cssSelector(selector[1]));
		}

		return this.form.querySelector(this.cssSelector(selector));
	}

	apiKeyField() : ?HTMLInputElement {
		return this.field(this.options.apiKeyField);
	}

	cssSelector(selector: string) : string {
		if (/^(input|select|textarea|div|#|\.)/.test(selector)) {
			return selector;
		}

		return `[name="${selector}"]`;
	}
}