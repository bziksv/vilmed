// noinspection JSUnresolvedVariable
import {Control} from "./Control";

const $ = window.YMarketJQuery ?? window.$;

export class SelectSkeleton extends Control {

	constructor(element: HTMLSelectElement, language: string) {
		super(element);
		this.$el = $(element);
		this.language = language;
	}

	boot() : void {
		this.$el.select2({
			width: '100%',
			language: this.language,
			tags: this.allowedCustom(),
		});
	}

	allowedCustom() : boolean {
		return {};
	}

	destroy() : void {
		this.$el.select2('destroy');
	}

	value() : string|string[] {
		if (!this.el.multiple) { return this.el.value; }

		const selected = [];

		for (const option of this.el.querySelectorAll('option')) {
			if (option.selected) {
				selected.push(option.value);
			}
		}

		return selected;
	}

	setValue(value: string|string[]) : void {
		const selected = this._toArray(value);
		const found = [];
		let placeholder;
		let first = true;
		let changed = false;

		for (const option of this.el.querySelectorAll('option')) {
			if (first && option.value === '') {
				placeholder = option;
				continue;
			}

			const matched = (selected.indexOf(option.value) !== -1);

			if (matched) {
				found.push(option.value);
			}

			if (matched !== option.selected) {
				option.selected = matched;
				changed = true;
			}

			first = false;
		}

		if (this.allowedCustom()) {
			for (const selectedItem of selected) {
				if (found.indexOf(selectedItem) !== -1) { continue; }

				const option = document.createElement('option');
				option.textContent = selectedItem;

				this.el.appendChild(option);
				option.selected = true;
				found.push(selectedItem);

				changed = true;
			}
		}

		const placeholderSelected = (found.length === 0);

		if (placeholder != null && placeholder.selected !== placeholderSelected) {
			placeholder.selected = placeholderSelected;
			changed = true;
		}

		changed && this.$el.triggerHandler('change.select2');
	}

	focus() : void {
		this.$el.select2('close');
		this.$el.select2('open');
	}

	focused() : boolean {
		return this.$el.select2('isOpen');
	}

	_toArray(value: string|string[]|null) : string[] {
		if (value == null) { return []; }

		return Array.isArray(value) ? value : [ value ];
	}
}