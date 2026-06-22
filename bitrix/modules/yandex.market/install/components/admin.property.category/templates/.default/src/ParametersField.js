import {ParameterRow} from "./Parameters/ParameterRow";
import {FactoryMenu} from "./Parameters/FactoryMenu";
import {ParameterCollection} from "./Parameters/Dto/ParameterCollection";
import type {Parameter} from "./Parameters/Dto/Parameter";
import type {Locale} from "./Component/Locale";
import {ParametersRegistry} from "./Parameters/ParametersRegistry";
import {ParentRow} from "./Parameters/ParentRow";
import type {Row} from "./Parameters/Row";
import {UnknownParameter} from "./Parameters/Dto/UnknownParameter";
import type {State} from "./Component/State";

const $ = window.YMarketJQuery ?? window.$;

export class ParametersField {

	static defaults = {
		name: null,
		parentRowElement: '[data-entity="parentRow"]',
		rowElement: '[data-entity="parameterRow"]',
		addElement: '[data-entity="parametersFactory"]',
		hintElement: '[data-entity="addHint"]',
	};

	rows: Row[];
	rowsIndex;

	constructor(element: HTMLElement, parametersRegistry: ParametersRegistry, state: State, locale: Locale, options: Object = {}) {
		this.el = element;
		this.options = Object.assign({}, this.constructor.defaults, options);
		this.state = state;
		this.locale = locale;
		this.parametersRegistry = parametersRegistry;
		this.factoryMenu = new FactoryMenu(this.el.parentElement.querySelector(this.options.addElement), this, this.locale);

		this.bootHint();
		this.bootRows();
	}

	destroy() : void {
		this.destroyHint();
		this.destroyRows();
		this.factoryMenu.destroy();
	}

	reload(parameters: ParameterCollection, parentValues: Object<number, string|string[]>, onlySelfValue: boolean) : void {
		if (onlySelfValue) {
			this.replaceParents(parameters);
		} else {
			this.redrawParents(parameters, parentValues);
		}

		this.redrawParameters(parameters);
		this.redrawRequired(parameters);

		this.parametersRegistry.reset(parameters);
	}

	replaceParents(parameters: ParameterCollection) : void {
		for (const row of this.rows.slice()) {
			if (!(row instanceof ParentRow)) { continue; }

			const parameter = parameters.item(row.id);

			if (parameter == null) {
				this.delete(row);
				continue;
			}

			const value = row.value();
			const index = this.rows.indexOf(row);
			this.delete(row);

			const newRow = this.add(parameter, index + 1);
			newRow.setValue(value);
		}
	}

	redrawParents(parameters: ParameterCollection, parentValues: Object<number, string|string[]>) : void {
		const found = [];

		for (const row of this.rows.slice()) {
			if (!(row instanceof ParentRow)) {
				if (parentValues[row.id] != null) {
					this.delete(row);
				}
				continue;
			}

			if (parentValues[row.id] == null) {
				this.delete(row);
				continue;
			}

			found.push(row.id);
			row.setValue(parentValues[row.id]);
		}

		for (const [id, value] of Object.entries(parentValues)) {
			if (found.indexOf(+id) !== -1) { continue; }

			const parameter = parameters.item(+id) || new UnknownParameter(+id, Array.isArray(value), this.locale);
			const parent = this.addParent(parameter, 0);

			parent.setValue(value);
		}
	}

	addParent(parameter: Parameter) : ParentRow {
		const container = this.el.tBodies[0] || this.el;
		const row = ParentRow.make(parameter, this);

		container.insertAdjacentElement('afterbegin', row.el);
		row.render(parameter, this.values());

		this.rows.unshift(row);

		this.el.classList.remove('is--empty');

		return row;
	}

	redrawParameters(parameters: ParameterCollection) : void {
		for (const row of this.rows.slice()) {
			if (!(row instanceof ParameterRow)) { continue; }

			const parameter = parameters.item(row.id);

			if (parameter != null) {
				row.render(parameter, this.values());
			} else if (row.value()) {
				row.markDeprecated();
			} else {
				this.delete(row);
			}
		}
	}

	redrawRequired(parameters: ParameterCollection) : void {
		const exists = this.rows.map((row: Row) : number => row.id);
		const filled = this.rows.filter((row: Row) : boolean => !!row.value()).map((row: Row) : number => row.id);

		for (const parameter of parameters.all()) {
			if (!parameter.showByDefault() || exists.includes(parameter.id()) || !parameter.shownDependsOn(filled)) { continue; }

			this.add(parameter);
		}
	}

	add(parameter: Parameter, index: ?number = null) : ParameterRow {
		const container = this.el.tBodies[0] || this.el;
		const row = ParameterRow.make(parameter, `${this.options.name}[${this.rowsIndex}]`, this);

		if (index != null && this.rows[index] != null) {
			container.insertBefore(row.el, this.rows[index].el);
		} else {
			index = null;
			container.appendChild(row.el);
		}

		row.render(parameter, this.values());

		if (index != null) {
			this.rows.splice(index, 0, row);
		} else {
			this.rows.push(row);
		}

		++this.rowsIndex;

		this.el.classList.remove('is--empty');

		return row;
	}

	reflowDepended(id: number): void {
		this.parametersRegistry.collection()
			.then((collection: ParameterCollection) => {
				for (const parameter of collection.dependentOf(id)) {
					const row = this.row(parameter.id());

					if (row instanceof ParameterRow) {
						row.reflow(this.values());
					} else if (row == null && parameter.showByDefault()) {
						const anchor = this.row(id);

						if (anchor == null) { continue; }

						this.add(parameter, this.rows.indexOf(anchor) + 1);
					}
				}
			});
	}

	bootHint() : void {
		const hint = this.el.parentElement.querySelector(this.options.hintElement);

		this._hint = new BX.CHint({
			parent: hint,
			hint: this.locale.message('PARAMETERS_ADD_HINT'),
			show_timeout: 30,
		});
	}

	destroyHint() : void {
		this._hint.Destroy();
	}

	bootRows() : void {
		this.rows = [];
		this.rowsIndex = 0;

		for (const parentElement of this.el.querySelectorAll(this.options.parentRowElement)) {
			const row = new ParentRow(parentElement, this);
			row.initial();

			this.rows.push(row);
		}

		for (const element of this.el.querySelectorAll(this.options.rowElement)) {
			const row = new ParameterRow(element, this);
			row.initial();

			this.rows.push(row);
			++this.rowsIndex;
		}
	}

	destroyRows() : void {
		for (const row of this.rows) {
			row.destroy();
		}

		this.rows = [];
	}

	delete(row: Row) : void {
		const index = this.rows.indexOf(row);

		if (index === -1) { throw new Error('unknown item'); }

		const el = row.el;
		row.destroy();
		el.remove();

		this.rows.splice(index, 1);

		if (this.rows.length === 0) {
			this.el.classList.add('is--empty');
		}
	}

	fireChange() : void {
		$(this.el).trigger('change');
	}

	row(id: number) : ?Row {
		for (const row of this.rows) {
			if (row.id === id) {
				return row;
			}
		}

		return null;
	}

	values() : Object {
		const result = {};

		for (const row of this.rows) {
			result[row.id] = row.value();
		}

		return result;
	}
}
