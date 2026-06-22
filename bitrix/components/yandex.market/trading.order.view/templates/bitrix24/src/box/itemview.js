import { htmlToElement } from "../utils";
import Basket from "../basket/basket";

export default class ItemView {

	static defaults = {
		name: null,
		messages: {},
		actions: [],
		onChange: null,
	}

	constructor(options) {
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	destroy() {
		this.basket.destroy();
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}

	onlyForPart() : boolean {
		let result = false;

		for (const basketItem of this.basket.items) {
			if (basketItem.isPart()) {
				result = true;
				break;
			}
		}

		return result;
	}

	title() : string {
		return this.el.querySelector('.yamarket-basket-box__title').textContent.trim();
	}

	render(box: Object, basket: Object) : void {
		this.renderSelf(box, basket);
		this.renderBasket(box, basket);
	}

	renderSelf(box: Object, basket: Object) : void {
		this.el = htmlToElement(`<tbody>
			<tr>
				<td class="yamarket-basket-box" colspan="${this.columnCount(basket)}">
					<span class="yamarket-basket-box__title">
						${this.getMessage('BOX')}
						&numero;${box['NUMBER']}
					</span>
					${this.buildActions(box)}
				</td>
			</tr>
		</tbody>`, 'table');
	}

	hideSelf() : void {
		this.el.hidden = true;
	}

	renderBasket(box: Object, basket: Object) : void {
		this.basket = new Basket(this.basketOptions());
		this.basket.render(box['ITEMS'] ?? [], basket);
	}

	basketOptions() : Object {
		// noinspection JSUnresolvedReference
		return {
			name: this.options.name + `[ITEMS]`,
			mode: BX.UI.EntityEditorMode.view,
			messages: this.options.messages,
			actions: this.options.actions,
			onChange: this.options.onChange,
		};
	}

	columnCount(basket: Object) : number {
		return Object.keys(basket.COLUMNS).length + 1;
	}

	buildActions(box: Object) : string {
		return '';
	}

	detach() : void {
		this.el.remove();
		this.basket.el.remove();
	}

	mount(point: HTMLElement, after: ?HTMLElement = null) {
		if (after != null) {
			(after.nextElementSibling ?? after).after(this.el);
		} else {
			point.querySelector('table').appendChild(this.el);
		}

		this.basket.mount(this.el);
	}

	updateNumber(number: number) : void {
		const title = this.el.querySelector('.yamarket-basket-box__title');

		title.innerHTML = `${this.getMessage('BOX')} &numero;${number}`;
	}

	getName(field: string) : string {
		return this.options.name + '[' + field + ']';
	}
}