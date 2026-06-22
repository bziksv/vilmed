import type {Parameter} from "./Dto/parameter";
import type {Locale} from "../Component/Locale";
import type {ParametersField} from "../ParametersField";
import type {ParameterCollection} from "./Dto/ParameterCollection";
import {Row} from "./Row";

const BX = window.BX;

export class FactoryMenu {

	_opening = false;

	constructor(element: HTMLElement, field: ParametersField, locale: Locale) {
		this.el = element;
		this.field = field;
		this.locale = locale;
		// noinspection JSUnresolvedReference
		this._menu = new BX.CMenu({ parent: this.el });

		this.handleClick(true);
	}

	destroy() : void {
		this._menu = null; // no way to destroy
		this.handleClick(false);
	}

	handleClick(dir: boolean) : void {
		this.el[dir ? 'addEventListener' : 'removeEventListener']('click', this.onClick);
	}

	onClick = (evt: PointerEvent) : void => {
		evt.preventDefault();

		if (this._opening || this.isOpen()) { return; }

		this._opening = true;

		this.field.parametersRegistry.collection()
			.then((parameterCollection: ParameterCollection) => {
				this._opening = false;
				this.open(parameterCollection);
			})
			.catch((error: Error) => {
				this._opening = false;
				this.field.state.error(error);
			});
	}

	isOpen() : boolean {
		if (this._menu == null) { return false; }

		return (this._menu.DIV.style.display || '').toLowerCase() === 'block';
	}

	open(parameterCollection: ParameterCollection) : void {
		if (parameterCollection.count() === 0) {
			this.field.state.error(new Error(this.locale.message('EMPTY_PROPERTIES')));
			return;
		}

		// noinspection JSUnresolvedReference
		this._menu.setItems(this.menuItems(parameterCollection));
		// noinspection JSUnresolvedReference
		this._menu.Show();
	}

	select(parameter: Parameter) : void {
		this.field.add(parameter);
	}

	menuItems(parameterCollection: ParameterCollection) : Array {
		const exists = this.field.rows.map((row: Row) : number => row.id)
		const filled = this.field.rows.filter((row: Row) : boolean => !!row.value()).map((row: Row) : number => row.id);
		const parameters = this.availableParameters(parameterCollection, exists, filled);
		const result = [];

		for (const parameter of parameters) {
			result.push({
				TEXT: `${parameter.name()}${parameter.required() ? '*' : ''}`,
				HTML: `${parameter.name()}${parameter.required() ? '<span class="ym-category-required-flag">*</span>' : ''}`,
				ONCLICK: () => this.select(parameter),
			});
		}

		return result;
	}

	availableParameters(parameterCollection: ParameterCollection, exists: number[], filled: number[]) : Parameter[] {
		const result = [];

		for (const parameter of parameterCollection.all()) {
			if (exists.includes(parameter.id())) { continue; }

			if (parameter.shownDependsOn(filled)) {
				result.push(parameter);
			}
		}

		return result;
	}
}