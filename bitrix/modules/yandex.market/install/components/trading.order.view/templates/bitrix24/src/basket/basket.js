import ItemView from "./itemview";
import ItemEdit from "./itemedit";
import { htmlToElement } from "../utils";
import './basket.css';

export default class Basket {

	static defaults = {
		messages: {},
		mode: null,
		name: null,
		title: null,
		actions: [],
		onChange: null,
	}

	constructor(options: Object) {
		this.options = Object.assign({}, this.constructor.defaults, options);
		this.items = [];
		this.nextIndex = 0;
	}

	destroy() {
		for (const item of this.items) {
			item.destroy();
		}
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}

	sameBasketItems(basketItem: ItemView) : ItemView[] {
		const result = [];

		for (const item of this.items) {
			if (basketItem.id() === item.id()) {
				result.push(item);
			}
		}

		return result;
	}

	countChanges() {
		const result = [];

		for (const item of this.items) {
			if (!(item instanceof ItemEdit)) { continue; }

			const diff = item.getCountDiff();

			if (diff > 0) {
				result.push({
					name: item.getTitle(),
					diff: diff,
				});
			}
		}

		return result;
	}

	render(boxItems: Array, basket: Object) : void {
		this.renderSelf();

		this.renewItems(boxItems.length).forEach((basketItem: ItemView, index: number) : void => {
			basketItem.render(boxItems[index], basket);
			basketItem.mount(this.el);
		});
	}

	mount(point) : void {
		point.after(this.el);
	}

	renderSelf() {
		this.el = htmlToElement(`<tbody></tbody>`, 'table');
	}

	renewItems(count: number) : ItemView[] {
		this.destroyItems();

		return this.createItems(count);
	}

	destroyItems() {
		for (const item of this.items) {
			item.destroy();
		}

		this.items = [];
	}

	createItems(count: number) : ItemView[] {
		this.items = [];

		for (let i = 0; i < count; ++i) {
			this.items.push(this.createItem(i));
		}

		this.nextIndex = this.items.length;

		return this.items;
	}

	createItem(index) {
		const options = {
			messages: this.options.messages,
			name: `${this.options.name}[${index}]`,
			actions: this.options.actions,
			onChange: this.options.onChange,
		};

		if (this.options.mode === BX.UI.EntityEditorMode.edit) {
			return new ItemEdit(options);
		}

		return new ItemView(options);
	}

	moveItem(item: ItemView, count: ?number = null) : ItemView {
		const sameItems = this.sameBasketItems(item);

		if (sameItems.length > 0) {
			const sameItem = sameItems[0];

			if (count != null) {
				sameItem.setCount(sameItem.getCount() + count);
				sameItem.setInitialCount(sameItem.getInitialCount() + count);
			} else {
				sameItem.setCount(sameItem.getCount() + item.getCount());
				sameItem.setInitialCount(sameItem.getInitialCount() + item.getInitialCount());
			}

			item.destroy();

			return sameItem;
		}

		this.items.push(item);
		item.replaceName(`${this.options.name}[${this.nextIndex}]`);
		item.mount(this.el);

		if (count != null) {
			item.setCount(count);
			item.setInitialCount(count);
		}

		++this.nextIndex;

		return item;
	}

	detachItem(item: ItemView) : void {
		const index = this.items.indexOf(item);

		if (index === -1) { return; }

		this.items.splice(index, 1);
		item.el.remove();
	}
}