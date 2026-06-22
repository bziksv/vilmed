import type {ParametersField} from "../ParametersField";
import type {Parameter} from "./Dto/Parameter";
import type {Control} from "./ValueType/Control";
import {ParentControl} from "./ValueType/ParentControl";
import {Row} from "./Row";
import {htmlToElement} from "../Component/utils";

export class ParentRow extends Row {

	static make(parameter: Parameter, field: ParametersField) : ParentRow {
		const unit = parameter.defaultUnit();
		const element = htmlToElement(`<tr class="ym-category-parameter">
			<td class="ym-category-parameter__title">
				<span class="ym-category-parameter__label">${parameter.name()}${unit != null ? `, ${unit.name}` : ''}</span>
				<span class="ym-category-parameter__hint" data-entity="hint"></span>
			</td>
			<td class="ym-category-parameter__field" data-entity="field"></td>
			<td class="ym-category-parameter__actions"></td>
        </tr>`, 'table');

		return new ParentRow(element, field, {
			id: parameter.id(),
		});
	}

	initialControl() : Control {
		const control = new ParentControl(this.el.querySelector(this.options.valueElement), this.field.locale.language());
		control.boot();

		return control;
	}

	parameterControl(parameter: Parameter, name: string, values: Object<number, string|string[]>): Control {
		const field = this.el.querySelector(this.options.fieldElement);
		const control = ParentControl.make(parameter, this.field.locale.language());

		field.appendChild(control.el);
		control.boot();

		return control;
	}
}