import {Control} from "./Control";
import {TextControl} from "./TextControl";
import {EnumControl} from "./EnumControl";
import {BooleanControl} from "./BooleanControl";
import {NumberControl} from "./NumberControl";
import type {Parameter} from "../Dto/Parameter";
import type {Locale} from "../../Component/Locale";

export class Factory {

	static make(parameter: Parameter, name: string, locale: Locale) : Control {
		const type = parameter.type();

		if (type === 'ENUM') {
			return EnumControl.make(parameter, name, locale.language());
		} else if (type === 'BOOLEAN') {
			return BooleanControl.make(parameter, name, locale);
		} else if (type === 'NUMERIC') {
			return NumberControl.make(parameter, name);
		}

		return TextControl.make(parameter, name);
	}
}