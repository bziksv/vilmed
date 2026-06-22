import {SelectSkeleton} from "./SelectSkeleton";
import type {Parameter} from "../Dto/Parameter";
import {htmlToElement} from "../../Component/utils";

export class ParentControl extends SelectSkeleton {

	static make(parameter: Parameter, language: string) : ParentControl {
		const multiple = parameter.multiple();

		return new ParentControl(
			htmlToElement(`<select class="ym-category-parameter__control" ${multiple ? 'multiple' : ''} disabled></select>`),
			language
		);
	}

	setValue(value: string|string[]) : void {
		this.el.innerHTML = '';

		for (const item of this._toArray(value)) {
			const idMatches = /^(.*)\s\[\d+]$/.exec(item);
			const option = document.createElement('option');

			if (idMatches !== null) {
				option.value = item;
				option.textContent = idMatches[1];
			} else {
				option.textContent = item;
			}

			option.selected = true;
			this.el.appendChild(option);
		}
	}

	allowedCustom() : boolean {
		return true;
	}
}