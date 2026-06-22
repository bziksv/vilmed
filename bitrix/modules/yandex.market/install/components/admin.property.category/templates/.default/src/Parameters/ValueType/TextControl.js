import {Control} from "./Control";
import {htmlToElement} from "../../Component/utils";
import type {Parameter} from "../Dto/parameter";

export class TextControl extends Control {

	static make(parameter: Parameter, name: string) : TextControl {
		const maxLength = parameter.maxLength();

		return new TextControl(htmlToElement(`<input
		 	class="ym-category-parameter__control adm-input"
			type="text"
			name="${name}"
			${maxLength != null ? `maxlength="${maxLength}"` : ''}
		/>`));
	}

}