import type {Parameter} from "./Dto/Parameter";
import {Row} from "./Row";
import {InitialControl} from "./ValueType/InitialControl";
import {htmlToElement} from "../Component/utils";
import type {ParametersField} from "../ParametersField";
import {Factory} from "./ValueType/Factory";
import type {Control} from "./ValueType/Control";
import {EnumControl} from "./ValueType/EnumControl";

export class ParameterRow extends Row {

	static defaults = Object.assign({}, Row.defaults, {
		name: null,
		deleteElement: '[data-entity="delete"]',
	});

	static make(parameter: Parameter, name: string, field: ParametersField) : ParameterRow {
		const unit = parameter.defaultUnit();
		const element = htmlToElement(`<tr class="ym-category-parameter">
			<td class="ym-category-parameter__title">
				<input type="hidden" name="${name}[ID]" value="${parameter.id()}" />
				<input type="hidden" name="${name}[NAME]" value="${parameter.name()}" />
				${unit != null 
					? `<input type="hidden" name="${name}[UNIT]" value="${unit.name} [${unit.id}]" />`
					: ''}
				<span class="ym-category-parameter__label">${parameter.name()}${unit != null ? `, ${unit.name}` : ''}</span>
				<span class="ym-category-parameter__hint" data-entity="hint"></span>
			</td>
			<td class="ym-category-parameter__field" data-entity="field"></td>
			<td class="ym-category-parameter__actions">
				<button class="ym-category-parameter__delete" type="button" data-entity="delete">${field.locale.message('PARAMETER_DELETE')}</button>
			</td>
        </tr>`, 'table');

		return new ParameterRow(element, field, {
			id: parameter.id(),
			name: name,
		});
	}

	constructor(element: HTMLElement, field: ParametersField, options: Object = {}) {
		super(element, field, options);
		this.handleDelete(true);
	}

	destroy() {
		this.handleDelete(false);
		super.destroy();
	}

	handleDelete(dir: boolean) : void {
		const button = this.el.querySelector(this.options.deleteElement);

		button[dir ? 'addEventListener' : 'removeEventListener']('click', this.onDelete);
	}

	onChange = () => {
		this.field.reflowDepended(this.id);
	}

	onDelete = () => {
		this.field.delete(this);
		this.field.fireChange();
	}

	initialControl() : InitialControl {
		const control = new InitialControl(this.el.querySelector(this.options.valueElement), this.field.locale.language());
		control.boot();
		control.bindFocus(() => {
			// noinspection JSIgnoredPromiseFromCall
			this.initialLoad();
		});

		return control;
	}

	reflow(values: Object<number, string|string[]>) : void {
		if (!(this.control instanceof EnumControl)) { return; }

		this.control.reflow(values);
	}

	render(parameter: Parameter, values: Object<number, string|string[]>) : void {
		super.render(parameter, values);
		this.setDeprecated(false);
	}

	parameterControl(parameter: Parameter, name: string, values: Object<number, string|string[]>) : Control {
		const field = this.el.querySelector(this.options.fieldElement);
		const control = Factory.make(parameter, name, this.field.locale);

		if (control instanceof EnumControl) {
			control.reflow(values);
			control.bindChange(this.onChange);
		}

		field.appendChild(control.el);
		control.boot();

		return control;
	}

	markDeprecated(): void {
		this.setDeprecated(true);
		this.setRequired(false);
		this.setHint(this.field.locale.message('PARAMETER_DEPRECATED'));
	}

	setDeprecated(deprecated: boolean) : void {
		this.el.classList[deprecated ? 'add' : 'remove']('is--deprecated');
	}
}