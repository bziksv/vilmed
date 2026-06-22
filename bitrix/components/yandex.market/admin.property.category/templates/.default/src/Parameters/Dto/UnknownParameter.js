import {Parameter} from "./Parameter";
import type {Locale} from "../../Component/Locale";

export class UnknownParameter extends Parameter {
	constructor(id: number, multiple: boolean, locale: Locale) {
		super({
			id: id,
			multivalue: multiple,
			title: locale.message('UNKNOWN_PARAMETER', { 'ID': id }),
		});
	}
}