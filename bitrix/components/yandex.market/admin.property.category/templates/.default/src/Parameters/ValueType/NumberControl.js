import {Control} from "./Control";
import {htmlToElement} from "../../Component/utils";
import type {Parameter} from "../Dto/Parameter";

export class NumberControl extends Control {

	static make(parameter: Parameter, name: string) : NumberControl {
		const minValue = parameter.minValue();
		const maxValue = parameter.maxValue();

		return new NumberControl(htmlToElement(`<input
 			class="ym-category-parameter__control adm-input" 
			type="number" 
			name="${name}"
			${minValue != null ? `min="${minValue}"` : ''}
			${maxValue != null ? `max="${maxValue}"` : ''}
		/>`));
	}

	destroy() : void {
		this._handleInput(false);
		super.destroy();
	}

	boot() : void {
		this._handleInput(true);
	}

	_handleInput(dir: boolean) : void {
		this.el[dir ? 'addEventListener' : 'removeEventListener']('input', this._onInput);
		this.el[dir ? 'addEventListener' : 'removeEventListener']('change', this._onChange);
	}

	_onInput = () => {
		const value = parseFloat(this.el.value);
		const valid = (
			!Number.isNaN(value)
			&& (this.el.min == null || value >= this.el.min)
			&& (this.el.max == null || value <= this.el.max)
		);

		this._markInvalid(!valid);
	}

	_onChange = () => {
		const value = parseFloat(this.el.value);

		if (isNaN(value)) {
			this.el.value = '';
		} else if (this.el.min != null && value < this.el.min) {
			this.el.value = this.el.min;
		} else if (this.el.max != null && value > this.el.max) {
			this.el.value = this.el.max;
		}

		this._markInvalid(false);
	}

	_markInvalid(dir : boolean) : void {
		this.el.classList[dir ? 'add' : 'remove']('is--invalid');
	}
}