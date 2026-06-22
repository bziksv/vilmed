import {CategoryField} from "./CategoryField";
import {ParametersField} from "./ParametersField";
import {Transport} from "./Component/Transport";
import {Locale} from "./Component/Locale";
import {Form} from "./Component/Form";
import {State} from "./Component/State";
import {ParametersRegistry} from "./Parameters/ParametersRegistry";
import {ParametersPool} from "./Parameters/ParametersPool";

// noinspection JSUnusedGlobalSymbols
export class CategoryPanel {

    static defaults = {
        transport: null,
	    language: 'ru',
	    locale: null,
	    form: null,
	    category: null,
        categoryElement: '[data-entity="category"]',
	    parameters: null,
        parametersElement: '[data-entity="parameters"]',
	    parametersPool: null,
	    stateElement: '[data-entity="state"]',
    }

	_reloadTimeout;

	constructor(element: HTMLElement, options: Object = {}) {
		this.el = element;
		this.options = Object.assign({}, this.constructor.defaults, options);
		this.locale = new Locale(this.options.locale, this.options.language);
		this.transport = new Transport(this.options.transport);
		this.parametersPool = this.options.parametersPool != null ? this.options.parametersPool : new ParametersPool(this.transport);
		this.form = new Form(this.el.closest('form'), this.transport, this.parametersPool, this.formOptions());
		this.state = new State(element.querySelector(this.options.stateElement), this.locale);
		this.category = new CategoryField(element.querySelector(this.options.categoryElement), this.transport, this.locale, this.options.category);
		this.parameters = new ParametersField(
			element.querySelector(this.options.parametersElement),
			new ParametersRegistry(this.category, this.parametersPool, this.state, this.locale),
			this.state,
			this.locale,
			this.parametersOptions(this.category)
		);

		this.transport.configure(this.form.apiKeyField());
		this.category.handleChange(true, this.onCategoryChange);
		this.form.handleChange(true, this.onFormChange);
	}

	destroy() : void {
		clearTimeout(this._reloadTimeout);
		this.category.handleChange(false, this.onCategoryChange);
		this.form.handleChange(false, this.onFormChange);

		this.parameters.destroy();
		this.category.destroy();
		this.form.destroy();
	}

	formOptions() : Object {
		const options = Object.assign({}, this.options.form);

		if (this.el.dataset.formPayload != null) {
			options['payload'] = JSON.parse(this.el.dataset.formPayload);
		}

		return options;
	}

	parametersOptions(category: CategoryField) : Object {
		const options = Object.assign({}, this.options.parameters);

		if (options.name == null) {
			options.name = category.selectElement().prop('name').replace(/\[CATEGORY]$/, '[PARAMETERS]');
		}

		return options;
	}

	onCategoryChange = () : void => {
		this.reload();
	}

	onFormChange = () : void => {
		this.reloadDelayed();
	}

	reloadDelayed() : void {
		clearTimeout(this._reloadTimeout);

		// noinspection JSUnresolvedReference
		if (window.JCIBlockGroupFieldIsRunning) { return; }

		this._reloadTimeout = setTimeout(() => this.reload(), 200);
	}

	reload() {
		clearTimeout(this._reloadTimeout);
		this.state.loading();

		const category = this.category.value();

		this.form.reload(category)
			.then((data: Object) : void => {
				this.category.resetParent(data.parentCategory);
				this.parameters.reload(data.parameters, data.parentParameters, !!category);
				this.state.waiting();
			})
			.catch((error: Error) : void => {
				this.state.error(error);
			});
	}

	value() : Object {
		return {
			category: this.category.value(),
			parameters: this.parameters.values(),
		};
	}

	resetParent(parentValue: Object) : void {
		this.category.resetParent(parentValue.category);

		if (this.category.value()) { return; }

		this.parametersPool.get(parentValue.category)
			.then((parameterCollection) => {
				this.parameters.reload(parameterCollection, parentValue.parameters, false);
			});
	}
}