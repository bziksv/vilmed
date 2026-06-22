import Cis from "./cis";
import Digital from "./digital";
import {htmlToElement, pascalCase, kebabCase, findInputs} from "../utils";
import type SummarySkeleton from "./summaryskeleton";

export default class ItemView {

	static defaults = {
		messages: {},
		name: null,
		actions: [],
		onChange: null,
	}

	el;

	constructor(options: Object) {
		this.options = Object.assign({}, this.constructor.defaults, options);
		this._wires = [];
	}

	destroy() : void {
		this.unbind();
		this.forgetWires();
		this.options = {};
	}

	clone() : ItemView {
		const cloned = new this.constructor(this.options);
		cloned.el = this.el.cloneNode(true);

		for (const [key, wire] of Object.entries(this._wires)) {
			cloned.wire(key, wire.clone());
		}

		cloned.setupWires();
		cloned.bind();

		return cloned;
	}

	bind() : void {}

	unbind() : void {}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}

	getTitle() : string {
		return this.el.querySelector('[data-entity="NAME"]').textContent.trim();
	}

	id() : string {
		return this.getInputValue('ID');
	}

	render(item: Object, basket: Object) : void {
		const columns = Object.keys(basket.COLUMNS);
		const basketItem = basket.ITEMS[item.BASKET_KEY];

		columns.unshift('INDEX');

		this.forgetWires();

		this.el = htmlToElement(`<tr class="${item?.PARTIAL_COUNT?.CURRENT > 0 ? 'is--partial' : ''}">
			${columns.map((key) => this.renderColumn(item, basketItem, key)).join('')}
			${this.renderActions()}
		</tr>`, 'tbody');

		this.setupWires();
		this.bind();
	}

	mount(point: HTMLElement) {
		point.appendChild(this.el);
	}

	replaceName(newName: string) : void {
		const oldName = this.options.name;

		for (const [name, input] of Object.entries(findInputs(this.el))) {
			input.name = name.replace(oldName, newName);
		}

		for (const [, wire] of Object.entries(this._wires)) {
			wire.replaceName(oldName, newName);
		}

		this.options.name = newName;
	}

	renderColumn(item: Object, basketItem: Object, column: string) : string {
		const method = 'column' + pascalCase(column);

		return (
			method in this
				? this[method](item, basketItem, column)
				: this.columnDefault(item, basketItem, column)
		);
	}

	enableUp(enable: boolean = true) : void {}

	enableDown(enable: boolean = true) : void {}

	// noinspection JSUnusedGlobalSymbols
	columnIndex(item: Object, basketItem: Object, column: string) : string {
		return `<td class="for--${kebabCase(column)}">
			${this.indexHidden(item, basketItem)}
			${this.valueFormatted(item, basketItem, column)}
		</td>`;
	}

	indexHidden(item: Object, basketItem: Object) : string {
		const identifiersCount = this.value(item, basketItem, 'IDENTIFIERS_INITIAL_COUNT');

		return ([
			`<input type="hidden" name="${this.getName('ID')}" value="${this.value(item, basketItem, 'ID')}" />`,
			`<input type="hidden" name="${this.getName('INITIAL_BOX')}" value="${this.value(item, basketItem, 'INITIAL_BOX')}" />`,
			identifiersCount > 0
				? `<input type="hidden" name="${this.getName('IDENTIFIERS')}[INITIAL_COUNT]" value="${identifiersCount}" />`
				: '',
		]).join('');
	}

	// noinspection JSUnusedGlobalSymbols
	columnCis(item: Object, basketItem: Object, column: string) : string {
		const cis = new Cis({
			messages: this.options.messages,
			name: this.getName('IDENTIFIERS'),
			title: this.basketValue(basketItem, 'NAME'),
			markingType: this.basketValue(basketItem, 'MARKING_TYPE'),
			markingGroup: !!this.basketValue(basketItem, 'MARKING_GROUP'),
			instanceTypes: this.basketValue(basketItem, 'INSTANCE_TYPES'),
			count: +this.value(item, basketItem, 'COUNT'),
			offset: +this.value(item, basketItem, 'OFFSET'),
			total: +this.basketValue(basketItem, 'COUNT'),
			instances: this.basketValue(basketItem, 'INSTANCES'),
			internalInstances: this.basketValue(basketItem, 'INTERNAL_INSTANCES'),
			onChange: () => {
				this.fire('cisUpdate');
				this.options.onChange();
			},
		});

		this.wire(column, cis);

		return `<td class="for--${kebabCase(column)}" data-wire="${column}">${cis.build()}</td>`;
	}

	cis() : ?Cis {
		return this._wires['CIS'];
	}

	updateCisCount() : void {
		this.cis()?.updateCount(this.getCount(), this.getOffset());
	}

	// noinspection JSUnusedGlobalSymbols
	columnDigital(item: Object, basketItem: Object, column: string) : string {
		const digital = new Digital({
			messages: this.options.messages,
			name: this.getName('DIGITAL'),
			total: this.value(item, basketItem, 'COUNT'),
			items: this.value(item, basketItem, 'DIGITAL'),
			onChange: this.options.onChange,
		});

		this.wire(column, digital);

		return `<td class="for--${kebabCase(column)}" data-wire="${column}">${digital.build()}</td>`;
	}

	// noinspection JSUnusedGlobalSymbols
	columnSubsidy(item: Object, basketItem: Object, column: string) : string {
		const promos = this.value(item, basketItem, 'PROMOS');
		let content = this.valueFormatted(item, basketItem, column);

		if (promos != null && Array.isArray(promos)) {
			content += promos.map((promo) => `<div>${promo}</div>`).join('');
		}

		return `<td class="for--${kebabCase(column)}">${content}</td>`;
	}

	columnCount(item: Object, basketItem: Object, column: string) : string {
		const value = this.value(item, basketItem, column);
		const valueSanitized = parseInt(value) || '';
		const offset = parseInt(this.value(item, basketItem, 'OFFSET')) || 0;
		const partialCurrent = item?.PARTIAL_COUNT?.CURRENT;
		const partialTotal = item?.PARTIAL_COUNT?.TOTAL;

		return `<td class="for--${kebabCase(column)}">
			<input type="hidden" name="${this.getName('INITIAL_COUNT')}" value="${valueSanitized}" />
			<input type="hidden" name="${this.getName('COUNT')}" value="${valueSanitized}" />
			<input type="hidden" name="${this.getName('OFFSET')}" value="${offset}" />
			<input type="hidden" name="${this.getName('PARTIAL_CURRENT')}" value="${partialCurrent || ''}" />
			<input type="hidden" name="${this.getName('PARTIAL_TOTAL')}" value="${partialTotal || ''}" />
			<span data-entity="COUNT_TEXT">${this.valueFormatted(item, basketItem, column)} ${this.getMessage('ITEM_UNIT')}</span>
			<span data-entity="PARTIAL_TEXT">${this.partialText(partialCurrent, partialTotal)}</span>
		</td>`;
	}

	setCount(count: number) : void {
		const input = this.getInput('COUNT');
		const text = this.el.querySelector('[data-entity="COUNT_TEXT"]');

		input.value = count;
		text.textContent = `${count} ${this.getMessage('ITEM_UNIT')}`;
	}

	getCount() : number {
		return parseInt(this.getInputValue('COUNT')) || 0;
	}

	setInitialCount(count: number) : void {
		this.getInput('INITIAL_COUNT').value = count;
	}

	getInitialCount() : number {
		return parseInt(this.getInputValue('INITIAL_COUNT')) || 0;
	}

	setOffset(offset: number) : void {
		this.getInput('OFFSET').value = offset;
	}

	getOffset() : number {
		return parseInt(this.getInputValue('OFFSET')) || 0;
	}

	resetPart() : void {
		this.el.classList.remove('is--partial');
		this.getInput('PARTIAL_CURRENT').value = '';
		this.getInput('PARTIAL_TOTAL').value = '';
		this.el.querySelector('[data-entity="PARTIAL_TEXT"]').textContent = '';
	}

	setPart(index: number, total: number) : void {
		this.el.classList.add('is--partial');
		this.getInput('PARTIAL_CURRENT').value = index + 1;
		this.getInput('PARTIAL_TOTAL').value = total;
		this.el.querySelector('[data-entity="PARTIAL_TEXT"]').textContent = this.partialText(+index + 1, total);
	}

	partialText(current: number, total: number) : string {
		return (
			current > 0
				? `${this.getMessage('ITEM_PART')} ${current}/${total}`
				: ''
		);
	}

	isPart() : boolean {
		return parseInt(this.getInputValue('PARTIAL_CURRENT')) > 0;
	}

	isFinalPart() : boolean {
		const current = parseInt(this.getInputValue('PARTIAL_CURRENT')) || 0;
		const total = parseInt(this.getInputValue('PARTIAL_TOTAL')) || 0;

		return (current > 0 && current === total);
	}

	columnDefault(item: Object, basketItem: Object, column: string) : string {
		return `<td class="for--${kebabCase(column)}" data-entity="${column}">${this.valueFormatted(item, basketItem, column)}</td>`;
	}

	renderActions() : string {
		return '';
	}

	wire(key: string, instance: SummarySkeleton) : void {
		this._wires[key] = instance;
	}

	forgetWires() : void {
		this._wires = {};
	}

	setupWires() : void {
		for (const [key, instance] of Object.entries(this._wires)) {
			const column = this.el.querySelector(`[data-wire="${key}"]`);
			const element = column.firstElementChild;

			if (!element) { continue; }

			instance.setup(element);
		}
	}

	callWires(method: string, args: Array = []) : void {
		for (const [, instance] of Object.entries(this._wires)) {
			if (typeof instance[method] !== 'function') { return; }

			instance[method].apply(instance, args);
		}
	}

	valueFormatted(item: Object, basketItem: Object, column: string) : string {
		const formattedKey = column + '_FORMATTED';
		let result = '';

		if (item[column] != null) {
			result = item[column];
		} else if (basketItem[formattedKey] != null) {
			result = basketItem[formattedKey];
		} else if (basketItem[column] != null) {
			result = basketItem[column];
		}

		return result !== '' ? result : '&mdash;';
	}

	basketValue(basketItem: Object, column: string) {
		return basketItem[column];
	}

	value(item: Object, basketItem: Object, column: string) {
		if (item[column] != null) {
			return item[column];
		}

		return this.basketValue(basketItem, column);
	}

	getName(field: string) : string {
		return this.options.name + '[' + field + ']';
	}

	hasAction(type: string) : boolean {
		return this.options.actions.indexOf(type) !== -1;
	}

	fire(action: string, data: Object = {}) : void {
		this.el.dispatchEvent(new CustomEvent('yamarketBasketItemAction', {
			detail: Object.assign({
				item: this,
				action: action,
			}, data),
			bubbles: true,
		}));
	}

	getInputValue(field: string) : ?string {
		const input = this.getInput(field);
		let result;

		if (input == null) { return null; }

		if (input.type === 'checkbox') {
			result = input.checked ? input.value : null;
		} else {
			result = input.value;
		}

		return result;
	}

	getInput(field: string) : HTMLInputElement {
		const name = this.getName(field);

		return this.el.querySelector(`input[name="${name}"]`);
	}
}