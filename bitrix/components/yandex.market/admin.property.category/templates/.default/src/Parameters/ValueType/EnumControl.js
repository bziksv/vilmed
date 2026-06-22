import {htmlToElement} from "../../Component/utils";
import type {Parameter} from "../Dto/Parameter";
import {SelectSkeleton} from "./SelectSkeleton";

export class EnumControl extends SelectSkeleton {

	static make(parameter: Parameter, name: string, language: string) : EnumControl {
		const multiple = parameter.multiple();
		const element = htmlToElement(`<select class="ym-category-parameter__control" name="${name}${multiple ? '[]' : ''}" ${multiple ? 'multiple' : ''}>
			${multiple ? '' : '<option value="" disabled></option>'}
		</select>`);

		return new EnumControl(element,	language, parameter);
	}

	_changeCallback;

	constructor(element: HTMLSelectElement, language: string, parameter: Parameter) {
		super(element, language);
		this.parameter = parameter;
	}

	destroy() : void {
		this.parameter = null;
		this.unbindChange();
		super.destroy();
	}

	allowedCustom(): boolean {
		return this.parameter.allowCustom();
	}

	bindChange(callback: () => {}) : void {
		this._changeCallback = callback;
		this.$el.on('change', this._changeCallback);
	}

	unbindChange() : void {
		if (this._changeCallback == null) { return; }

		this.$el.off('change', this._changeCallback);
		this._changeCallback = null;
	}

	reflow(selected: Object<number, string|string[]>) : void {
		const selfSelected = this._toArray(selected[this.parameter.id()]);
		const existOptions = this.el.querySelectorAll('option');
		const placeholder = existOptions[0] && existOptions[0].disabled ? existOptions[0] : null;
		const allowedCustom = this.allowedCustom();
		let existIndex = placeholder != null ? 1 : 0; // after placeholder
		let selectedFound = [];

		for (const value of this._activeValues(selected)) {
			let matchedOption;

			while (existOptions[existIndex] != null) {
				const existOption = existOptions[existIndex];

				if (existOption.dataset.id == null) { // custom
					if (allowedCustom && selfSelected.includes(existOption.value)) {
						existOption.selected = true;
						selectedFound.push(existOption.value);
						++existIndex;
						continue;
					}
				} else if (+existOption.dataset.id === value.id) {
					matchedOption = existOption;
					break;
				}

				existOption.remove();
				++existIndex;
			}

			if (matchedOption == null) {
				const anchor = existOptions[existIndex + 1];
				matchedOption = document.createElement('option');
				matchedOption.value = `${value.value} [${value.id}]`;
				matchedOption.textContent = value.value;
				matchedOption.dataset.id = value.id;

				if (anchor) {
					this.el.insertBefore(matchedOption, anchor);
				} else {
					this.el.appendChild(matchedOption);
				}
			}

			const optionSelected = selfSelected.includes(matchedOption.value);
			matchedOption.selected = optionSelected;

			if (optionSelected) {
				selectedFound.push(matchedOption.value);
			}
		}

		if (placeholder != null) {
			placeholder.selected = (selectedFound.length === 0);
		}
	}

	_activeValues(selected: Object<number, string|string[]>) : Object[] {
		const values = this.parameter.values();
		const restricted = this._restricted(selected);

		if (restricted === null) { return values; }

		const restrictedValues = [];

		for (const value of values) {
			if (restricted.includes(value.id)) {
				restrictedValues.push(value);
			}
		}

		return restrictedValues;
	}

	_restricted(selected: Object<number, string|string[]>) : ?number[] {
		const restrictions = this.parameter.valueRestrictions();

		if (restrictions == null) { return null; }

		let restricted = null;

		for (const restriction of restrictions) {
			const limitingId = restriction['limitingParameterId'];

			if (selected[limitingId] == null) { continue; }

			const siblingValue = this._castOptionIds(selected[limitingId]);

			for (const limitedValue of restriction['limitedValues']) {
				if (!siblingValue.includes(limitedValue['limitingOptionValueId'])) { continue; }

				if (restricted == null) {
					restricted = limitedValue['optionValueIds'];
					continue;
				}

				const newRestricted = [];

				for (const id of restricted) {
					if (limitedValue['optionValueIds'].includes(id)) {
						newRestricted.push(id);
					}
				}

				restricted = newRestricted;
			}
		}

		return restricted;
	}

	_castOptionIds(selected) : number[] {
		if (selected == null) { return []; }

		return this._toArray(selected)
			.map((item: string) : ?number => {
				const idMatches = /\[(\d+)]$/.exec(item);
				return idMatches ? +idMatches[1] : null;
			})
			.filter((item: ?number) : boolean => item != null);
	}
}