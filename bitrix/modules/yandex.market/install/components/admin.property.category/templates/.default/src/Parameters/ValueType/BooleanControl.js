import {htmlToElement} from "../../Component/utils";
import type {Parameter} from "../Dto/Parameter";
import type {Locale} from "../../Component/Locale";
import {SelectSkeleton} from "./SelectSkeleton";

export class BooleanControl extends SelectSkeleton {

	static make(parameter: Parameter, name: string, locale: Locale) : BooleanControl {
		const element = htmlToElement(`<select class="ym-category-parameter__control" name="${name}">
			<option value="Y">${locale.message('BOOLEAN_Y')}</option>
			<option value="N">${locale.message('BOOLEAN_N')}</option>
		</select>`);

		return new BooleanControl(element, locale.language());
	}

}