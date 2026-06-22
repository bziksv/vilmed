import type {ParametersField} from "../ParametersField";
import type {Parameter} from "./Dto/Parameter";
import type {Control} from "./ValueType/Control";

const BX = window.BX;

export class Row {

	static defaults = {
		id: null,
		hintElement: '[data-entity="hint"]',
		fieldElement: '[data-entity="field"]',
		valueElement: '[data-entity="value"]',
	}

	control: Control;
	_hintHover: boolean;

	constructor(element: HTMLElement, field: ParametersField, options: Object = {}) {
		this.el = element;
		this.field = field;
		this.options = Object.assign({}, this.constructor.defaults, this.el.dataset, options);
		this.id = +(this.options.id || this.el.dataset.id);
	}

	destroy() : void {
		this.field.parametersRegistry.stopWait(this.id, this.onParameterWait);
		this.handleHintHover(false);
		this.control.destroy();
		this.control = null;
		this.options = null;
		this.el = null;
	}

	initial() : void {
		this.handleHintHover(true);
		this.control = this.initialControl();
		this.field.parametersRegistry.wait(this.id, this.onParameterWait);
	}

	onParameterWait = (parameter: Parameter) : void => {
		this.render(parameter, this.field.values());
	}

	initialControl() : Control {
		throw new Error('not implemented');
	}

	initialLoad() : Promise {
		return this.field.parametersRegistry.initialLoad();
	}

	render(parameter: Parameter, values: Object<number, string|string[]>) : void {
		this.handleHintHover(false);
		this.setHint(parameter.description());
		this.setRequired(parameter.required());
		this.renderControl(parameter, values);
	}

	renderControl(parameter: Parameter, values: Object<number, string|string[]>) : void {
		if (this.control != null) {
			const name = this.control.el.name.replace(/\[]$/, '');
			const value = this.control.value();
			const focused = this.control.focused();

			this.control.el.remove();
			this.control.destroy();

			this.control = this.parameterControl(parameter, name, values);
			this.control.setValue(value);

			if (focused) {
				this.control.focus();
			}

			return;
		}

		this.control = this.parameterControl(parameter, `${this.options.name}[VALUE]`, values);
	}

	parameterControl(parameter: Parameter, name: string, values: Object<number, string|string[]>) : Control {
		throw new Error('not implemented');
	}

	handleHintHover(dir: boolean) : void {
		const hint = this.el.querySelector(this.options.hintElement);

		hint[dir ? 'addEventListener' : 'removeEventListener']('mouseenter', this.onHintEnter);
		hint[dir ? 'addEventListener' : 'removeEventListener']('mouseleave', this.onHintLeave);
	}

	onHintEnter = () : void => {
		if (this._hintHover == null) {
			this.initialLoad().then(() => {
				this.handleHintHover(false);
				// noinspection JSUnresolvedReference
				this._hintHover && this._hint && this._hint.Show();
			});
		}

		this._hintHover = true;
	}

	onHintLeave = () : void => {
		this._hintHover = false;
	}

	setHint(text: ?string) : void {
		const hint = this.el.querySelector(this.options.hintElement);

		if (!text) {
			// noinspection JSUnresolvedReference
			this._hint && this._hint.Destroy();
			this._hint = null;
			hint.classList.add('is--hidden');
			return;
		}

		if (this._hint != null) {
			// noinspection JSUnresolvedReference
			this._hint.CONTENT ? this._hint.setContent(text) : (this._hint.HINT = text);
			return;
		}

		hint.classList.remove('is--hidden');

		// noinspection JSUnresolvedReference
		this._hint = new BX.CHint({
			parent: hint,
			hint: text,
			show_timeout: 30,
		});
	}

	setRequired(required: boolean) : void {
		this.el.classList[required ? 'add' : 'remove']('is--required');
	}

	setValue(value: string|string[]) : void {
		this.control.setValue(value);
	}

	value() {
		return this.control.value();
	}
}