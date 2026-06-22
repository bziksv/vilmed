const $ = window.YMarketJQuery || window.jQuery;
const BX = window.BX;

// noinspection JSUnusedGlobalSymbols
export class CatalogCategory {

    static defaults = {
	    rowElement: '.ym-section-category__row',
	    expandElement: '.ym-section-category__expand',
	    controlElement: '.ym-category-panel',
	    form: {},
	    transport: {},
	    locale: {},
        language: 'ru',
    }

	_controls = {};
	_parametersPool;

    constructor(element: HTMLElement, options: Object = {}) {
        this.el = element;
        this.options = Object.assign({}, this.constructor.defaults, options);

        this.handleExpand();
		this.bootRows();
    }

	handleExpand() : void {
	    for (const button of this.el.querySelectorAll(this.options.expandElement)) {
		    button.addEventListener('click', this.onExpand);
	    }
    }

	handleChange(row: HTMLElement) : void {
		$(row).on('change', this.onChange);
	}

	onExpand = (evt) : void => {
		const button = evt.currentTarget;
		const row = button.closest(this.options.rowElement);
		const needActivate = !button.classList.contains('is--active');

		this.toggleRows(row, needActivate);
		this.toggleExpand(button, needActivate);
	}

	onChange = (evt) : void => {
		const row = evt.currentTarget;
		const control = this.bootControl(row);

		this.passParent(row, this.fulfillParentValue(row, control.value()));
	}

	bootRows() : void {
		for (const row of this.el.querySelectorAll(this.options.rowElement)) {
			if (row.hidden) { continue; }

			this.bootControl(row);
		}
	}

    // noinspection JSUnresolvedReference
	bootControl(row: HTMLElement) : BX.YandexMarket.Admin.Property.CategoryPanel {
		const sectionId = +row.dataset.id;

		if (this._controls[sectionId] != null) {
			return this._controls[sectionId];
		}

	    const control = row.querySelector(this.options.controlElement);

		// noinspection JSUnresolvedReference
	    this._controls[sectionId] = new BX.YandexMarket.Admin.Property.CategoryPanel(control, {
		    transport: this.options.transport,
		    form: Object.assign({}, this.options.form, {
			    parentValue: () => {
				    const parentRow = this.getParent(row);

				    if (parentRow == null) { return null; }

				    return this.fulfillParentValue(parentRow, this.bootControl(parentRow).value());
			    }
		    }),
            locale: this.options.locale,
            language: this.options.language,
		    parametersPool: this._parametersPool,
        });

		if (this._parametersPool == null) {
			this._parametersPool = this._controls[sectionId].parametersPool;
		}

		if (this.hasChildren(row)) {
			this.handleChange(row);
		}

		return this._controls[sectionId];
    }

	hasChildren(row: HTMLElement) : boolean {
		let nextRow = row.nextElementSibling;

		while (nextRow != null) {
			if (nextRow.dataset.parent === row.dataset.id) {
				return true;
			}

			if (nextRow.dataset.parent != null) { break; }

			nextRow = nextRow.nextElementSibling;
		}

		return false;
	}

	getParent(row: HTMLElement) : ?HTMLElement {
		if (row.dataset.parent == null) { return null; }

		let prevRow = row.previousElementSibling;

		while (prevRow != null) {
			if (prevRow.dataset.id === row.dataset.parent) {
				return prevRow;
			}

			prevRow = prevRow.previousElementSibling;
		}

		return null;
	}

	getChildren(row: HTMLElement) : HTMLElement[] {
		const result = [];
		let nextRow = row.nextElementSibling;

		while (nextRow != null) {
			if (nextRow.dataset.parent === row.dataset.id) {
				result.push(nextRow);
			}

			nextRow = nextRow.nextElementSibling;
		}

		return result;
	}

	passParent(row: HTMLElement, value: Object) : void {
		for (const child of this.getChildren(row)) {
			const childControl = this.bootControl(child);

			childControl.resetParent(value);

			const childValue = childControl.value();

			if (childValue.category) { continue; }

			this.passParent(child, this.mergeParentValue(childValue, value));
		}
	}

	fulfillParentValue(row: HTMLElement, value: Object) : Object {
		if (value.category) { return value; }

		const parent = this.getParent(row);

		if (parent == null) { return value; }

		const parentValue = this.bootControl(parent).value();
		const mergedValue = this.mergeParentValue(value, parentValue);

		if (mergedValue.category) { return mergedValue; }

		return this.fulfillParentValue(parent, mergedValue);
	}

	mergeParentValue(selfValue: Object, parentValue: Object) : Object {
		if (selfValue.category) { return selfValue; }

		const parameters = Object.assign({}, parentValue.parameters, selfValue.parameters);

		for (const [parameterId, parameterValue] of Object.entries(parameters)) {
			if (parameterValue == null || parameterValue === '') {
				delete parameters[parameterId];
			}
		}

		return {
			category: parentValue.category,
			parameters: parameters,
		};
	}

    toggleRows = (row: HTMLElement, dir: boolean) : void => {
	    let nextRow = row;

		// noinspection JSAssignmentUsedAsCondition
	    while (nextRow = nextRow.nextElementSibling) {
			if (nextRow.dataset.parent !== row.dataset.id) { continue; }

			const wasActive = !nextRow.hidden;

			if (wasActive === dir) { continue; }

		    nextRow.hidden = !dir;

			if (dir) {
				this.bootControl(nextRow);
			} else {
				const expand = nextRow.querySelector(this.options.expandElement);

				if (expand == null || !expand.classList.contains('is--active')) { continue; }

				this.toggleRows(nextRow, false);
				this.toggleExpand(expand, false);
			}
		}
    }

	toggleExpand(button: HTMLElement, dir: boolean) {
		if (button.classList.contains('is--active') === dir) { return; }

		const alt = button.dataset.alt;
		const textElement = button.querySelector('span') || button;
		const text = textElement.textContent;

		button.classList.toggle('is--active', dir);
		textElement.textContent = alt;
		button.dataset.alt = text;
	}
}