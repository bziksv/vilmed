import Field from "./field";
import ItemView from "./itemview";
import ItemEdit from "./itemedit";
import BasketItem from "../basket/itemview";

export default class Collection {

	static defaults = {
		name: null,
		actions: [],
		messages: {},
		onChange: null,
	}

	items: ItemView[];
	nextIndex: number = 0;
	anchor: HTMLElement;

	constructor(options) {
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	destroy() : void {
		this.destroyItems();
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}

	movableBox(box: ItemView, direction: boolean) : ?ItemView {
		return direction ? this.nextMovableBox(box) : this.previousMovableBox(box);
	}

	previousMovableBox(box: ItemView) : ?ItemView {
		let previous;
		let result;

		for (const item of this.items) {
			if (item === box) {
				result = previous;
				break;
			}

			if (!item.onlyForPart()) {
				previous = item;
			}
		}

		return result;
	}

	nextMovableBox(box: ItemView) : ?ItemView {
		let found = false;
		let result;

		for (const item of this.items) {
			if (found && !item.onlyForPart()) {
				result = item;
				break;
			}

			if (item === box) {
				found = true;
			}
		}

		return result;
	}

	render(boxes: Array, basket: Object, mode: number) : void {
		const items = [];
		let index = 0;

		this.destroyItems();

		for (const one of boxes) {
			const box = this.createItem(index, mode);

			box.render(one, basket);
			items.push(box);

			++index;
		}

		this.items = items;
		this.nextIndex = index;
	}

	mount(point: HTMLElement) : void {
		this.anchor = point;

		for (const item of this.items) {
			item.mount(point);
		}
	}

	addItem(basket: Object, mode: number) : ItemView {
		const number = this.items.length + 1;
		const box = this.createItem(this.nextIndex, mode);
		const last = this.items[this.items.length - 1]?.el;

		box.render({ 'NUMBER': number }, basket);
		box.mount(this.anchor, last);

		this.items.push(box);
		++this.nextIndex;

		return box;
	}

	destroyItems() : void {
		if (this.items == null) { return; }

		for (const item of this.items) {
			item.destroy();
		}

		this.items = [];
	}

	createItem(index: number, mode: number) : ItemView {
		const options = {
			name: this.options.name + `[${index}]`,
			messages: this.options.messages,
			actions: this.options.actions,
			onChange: this.options.onChange,
		};

		// noinspection JSUnresolvedReference
		if (mode === BX.UI.EntityEditorMode.edit) {
			return new ItemEdit(options);
		}

		return new ItemView(options);
	}

	deleteItem(item: ItemView) : void {
		const index = this.items.indexOf(item);

		if (index === -1) { return; }

		this.items.splice(index, 1);
		item.detach();
		item.destroy();
	}

	refresh(basketItem: BasketItem = null) : void {
		if (basketItem != null) {
			this.refreshBasketOffset(basketItem);
		}

		this.refreshBoxDelete();
		this.refreshBasketMove();
		this.refreshNumber();
	}

	refreshNumber() : void {
		let num = 1;

		for (const box of this.items) {
			box.updateNumber(num);
			++num;
		}
	}

	basketItemBox(basketItem: BasketItem) : ItemView {
		let result;

		for (const box of this.items) {
			for (const sibling of box.basket.items) {
				if (sibling === basketItem) {
					result = box;
					break;
				}
			}

			if (result != null) { break; }
		}

		return result;
	}

	refreshBoxDelete() : void {
		const canDelete = (this.items.length > 1);
		const canEdit = (this.options.actions.indexOf(Field.ACTION_BOX) !== -1);

		for (const box of this.items) {
			if (!canDelete && !canEdit) {
				box.hideSelf();
			} else if (box instanceof ItemEdit) {
				box.enableDelete(canDelete);
			}
		}
	}

	refreshBasketMove() : void {
		if ((this.options.actions.indexOf(Field.ACTION_BOX) === -1)) { return; }

		for (const box of this.items) {
			const onlyForPart = box.onlyForPart();
			const hasPrevious = (!onlyForPart && this.previousMovableBox(box) != null);
			const hasNext = (!onlyForPart && this.nextMovableBox(box) != null);

			for (const basketItem of box.basket.items) {
				basketItem.enableUp(hasPrevious);
				basketItem.enableDown(
					hasNext
					|| (
						!onlyForPart
						&& (box.basket.items.length > 1 || basketItem.getCount() > 1)
					)
				);
			}
		}
	}

	sameBasketItems(basketItem: BasketItem) : BasketItem[] {
		let result = [];

		for (const box of this.items) {
			result = result.concat(box.basket.sameBasketItems(basketItem));
		}

		return result;
	}

	refreshBasketOffset(basketItem: BasketItem) : void {
		const sameItems = this.sameBasketItems(basketItem);
		let offset = 0;

		for (const sameItem of sameItems) {
			sameItem.setOffset(offset);

			if (!sameItem.isPart() || sameItem.isFinalPart()) {
				offset += sameItem.getInitialCount();
			}

			sameItem.updateCisCount();
		}
	}

}