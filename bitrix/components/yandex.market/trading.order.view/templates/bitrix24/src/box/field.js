import ReferenceField from "../reference/field";
import {htmlToElement, kebabCase} from "../utils";
import ItemView from './itemview';
import BasketItem from '../basket/itemview';
import BasketItemMoveModal from "./basketitemmovemodal";
import BasketItemSplitModal from "./basketitemsplitmodal";
import BoxOnlyPartDeleteModal from "./boxonlypartdeletemodal";
import Collection from "./collection";

export default class Field extends ReferenceField {
	static ACTION_BOX = 'box';
	static ACTION_ITEM = 'item';
	static ACTION_CIS = 'cis';
	static ACTION_DIGITAL = 'digital';

	static messages = {}
	static defaults = {
		name: 'BOX',
		actions: [],
	}

	boxCollection: Collection;
	payloadBasket: Object;

	static create(id, settings) : Field {
		const instance = new Field();
		instance.initialize(id, settings);

		return instance;
	}

	bind() : void {
		this.handleBoxDelete(true);
		this.handleBasketItemAction(true);
	}

	unbind() : void {
		if (this.el == null) { return; }

		this.handleBoxDelete(false);
		this.handleBasketItemAction(false);
	}

	handleBoxDelete(dir: boolean) : void {
		this.el[dir ? 'addEventListener' : 'removeEventListener']('yamarketBoxDelete', this.onBoxDelete);
	}

	handleBasketItemAction(dir: boolean) {
		this.el[dir ? 'addEventListener' : 'removeEventListener']('yamarketBasketItemAction', this.onBasketItemAction);
	}

	onChange = () => {
		if (
			this._mode !== BX.UI.EntityEditorMode.edit
			&& (this.hasAction(Field.ACTION_CIS) || this.hasAction(Field.ACTION_DIGITAL))
		) {
			this._mode = BX.UI.EntityEditorMode.edit;
			this._editor.showToolPanel();
			this._editor.registerActiveControl(this);
		}

		this._changeHandler();
	}

	onBoxDelete = (evt) => {
		const box: ItemView = evt.detail.item;

		if (box.onlyForPart()) {
			(new BoxOnlyPartDeleteModal({ messages: Field.messages }))
				.open()
				.then(() => {
					for (const basketItem of box.basket.items) {
						this.cancelSplitBasketItem(basketItem);
					}
				});

			return;
		}

		const newBox = this.boxCollection.previousMovableBox(box) ?? this.boxCollection.nextMovableBox(box);

		if (newBox == null) { return; }

		for (const basketItem of box.basket.items) {
			this.moveBasketItem(basketItem, newBox);
		}

		this.boxCollection.deleteItem(box);
		this.boxCollection.refresh();
		this.onChange();
	}

	onBasketItemAction = (evt) : void => {
		const basketItem = evt.detail.item;
		const action = evt.detail.action;

		if (action === 'cisUpdate') {
			this.copyCis(basketItem);
		} else if (action === 'move') {
			this.startMoveBasketItem(basketItem, evt.detail.direction === 'down');
		} else if (action === 'split') {
			this.startSplitBasketItem(basketItem);
		} else if (action === 'cancelSplit') {
			this.cancelSplitBasketItem(basketItem);
		}
	}

	countChanges() : Array {
		let result = [];

		for (const box of this.boxCollection.items) {
			result = result.concat(box.basket.countChanges());
		}

		return result;
	}

	copyCis(basketItem: BasketItem) : void {
		const siblings = this.boxCollection.sameBasketItems(basketItem);
		const values = basketItem.cis()?.getValues();

		if (siblings.length <= 1 || values == null) { return; }

		for (const sibling of siblings) {
			if (sibling === basketItem) { continue; }

			sibling.cis()?.setValues(values);
		}
	}

	startMoveBasketItem(basketItem: BasketItem, direction: boolean) : void {
		const oldBox = this.boxCollection.basketItemBox(basketItem);
		const selectedBox = this.boxCollection.movableBox(oldBox, direction);
		const count = basketItem.getCount();

		if (basketItem.getCount() <= 1) {
			const newItem = this.moveBasketItem(basketItem, selectedBox ?? this.addBox());

			this.afterMoveBasketItem(newItem);
			return;
		}

		(new BasketItemMoveModal({
			count: count,
			current: oldBox,
			selected: selectedBox,
			items: this.boxCollection.items,
			messages: Field.messages,
		}))
			.open()
			.then((resolved: Array) : void => {
				let [newBox: ?ItemView, moveCount: number] = resolved;
				let newItem;

				if (newBox == null) { newBox = this.addBox(); }

				if (moveCount < count) {
					newItem = this.moveBasketItem(basketItem, newBox, moveCount, true);

					basketItem.setCount(basketItem.getCount() - moveCount);
					basketItem.setInitialCount(basketItem.getInitialCount() - moveCount);
				} else {
					newItem = this.moveBasketItem(basketItem, newBox);
				}

				this.afterMoveBasketItem(newItem);
			});
	}

	moveBasketItem(basketItem: BasketItem, newBox: ItemView, count: ?number = null, needClone: boolean = false) : BasketItem {
		if (needClone) {
			 basketItem = basketItem.clone();
		} else {
			const oldBox = this.boxCollection.basketItemBox(basketItem);

			oldBox.basket.detachItem(basketItem);

			if (oldBox.basket.items.length === 0) {
				this.boxCollection.deleteItem(oldBox);
			}
		}

		return newBox.basket.moveItem(basketItem, count);
	}

	afterMoveBasketItem(basketItem: BasketItem) : void {
		this.boxCollection.refresh(basketItem);
		this.onChange();
	}

	startSplitBasketItem(basketItem: BasketItem) : void {
		(new BasketItemSplitModal({ messages: Field.messages }))
			.open()
			.then((count: number) : void => this.splitBasketItem(basketItem, count));
	}

	splitBasketItem(basketItem: BasketItem, total: number) : void {
		const [deletedCount, deletedInitialCount] = this.removeBasketItemSiblings(basketItem);
		const count = (basketItem.getCount() || 1) + deletedCount;
		const initialCount = (basketItem.getInitialCount() || 1) + deletedInitialCount;

		for (let productIndex = 0; productIndex < count; ++productIndex) {
			for (let i = 0; i < total; ++i) {
				const isSelf = (productIndex === 0 && i === 0);
				const box = this.addBox();
				const newItem = this.moveBasketItem(basketItem, box, 1, !isSelf);

				newItem.setPart(i, total);

				if (productIndex === 0) {
					newItem.setInitialCount(1 + (initialCount - count));
				}
			}
		}

		this.afterMoveBasketItem(basketItem);
	}

	removeBasketItemSiblings(basketItem: BasketItem) : [number, number] {
		const sameItems = this.boxCollection.sameBasketItems(basketItem);
		let deletedCount = 0;
		let deletedInitialCount = 0;

		for (const sameItem of sameItems) {
			if (sameItem === basketItem) { continue; }

			const box = this.boxCollection.basketItemBox(sameItem);

			deletedCount += sameItem.getCount() || 0;
			deletedInitialCount += sameItem.getInitialCount() || 0;

			box.basket.detachItem(sameItem);

			if (box.basket.items.length === 0) {
				this.boxCollection.deleteItem(box);
			}
		}

		return [deletedCount, deletedInitialCount];
	}

	cancelSplitBasketItem(basketItem: BasketItem) : void {
		const siblings = this.boxCollection.sameBasketItems(basketItem);
		const [count, initialCount] = this.countSiblings(siblings);
		let firstSibling = null;

		for (const sibling of siblings) {
			if (firstSibling == null) {
				const box = this.boxCollection.basketItemBox(sibling);
				const previousBox = this.boxCollection.previousMovableBox(box) ?? this.boxCollection.nextMovableBox(box);

				sibling.resetPart();
				sibling.setCount(count);
				sibling.setInitialCount(initialCount);
				sibling.setOffset(0);
				sibling.updateCisCount();

				if (previousBox != null) {
					this.moveBasketItem(sibling, previousBox);
				}

				firstSibling = sibling;
				continue;
			}

			const box = this.boxCollection.basketItemBox(sibling);

			box.basket.detachItem(sibling);

			if (box.basket.items.length === 0) {
				this.boxCollection.deleteItem(box);
			}
		}

		firstSibling && this.afterMoveBasketItem(firstSibling);
	}

	countSiblings(siblings: BasketItem[]) : [number, number] {
		let count = 0;
		let initialCount = 0;

		for (const sibling of siblings) {
			if (!sibling.isPart() || sibling.isFinalPart()) {
				count += sibling.getCount() || 0;
				initialCount += sibling.getInitialCount() || 0;
			}
		}

		return [count, initialCount];
	}

	addBox() : ItemView {
		return this.boxCollection.addItem(this.payloadBasket, this._mode);
	}

	render(payload: Object) : void {
		this.unbind();

		this.options.actions = payload.ACTIONS;
		this.payloadBasket = payload.BASKET;

		this.renderSelf(payload['BOX'].length);
		this.renderBoxes(payload['BOX']);

		this._wrapper.appendChild(this.el);
		this.bind();
	}

	renderSelf(boxCount: number) : void {
		this.el = htmlToElement(`<div class="yamarket-basket">
			<input type="hidden" name="BOX_INITIAL_COUNT" value="${boxCount}" />
			<div class="yamarket-basket-table-viewport">
				<table class="yamarket-basket-table">
					${this.renderHeader()}
				</table>
			</div>
			${this.renderSummary()}
		</div>`);
	}

	renderHeader() : string {
		return `<thead>
			<tr>
				<td class="for--index">&numero;</td>
				${Object.keys(this.payloadBasket.COLUMNS)
					.map((key) => `<td class="for--${kebabCase(key)}">${this.columnTitle(key)}</td>`)
					.join('')}
				${this.isInEditMode() && this.hasAction(Field.ACTION_ITEM) ? '<td class="for--delete">&nbsp;</td>' : ''}
			</tr>
		</thead>`;
	}

	columnTitle(key: string) : string {
		const langKey = 'HEADER_' + key;
		const lang = this.getMessage(langKey);

		return lang !== langKey ? lang : this.payloadBasket.COLUMNS[key];
	}

	renderSummary() : string {
		if (this.payloadBasket.SUMMARY.length === 0) { return ''; }

		return `<div class="yamarket-basket-summary">
			${this.payloadBasket.SUMMARY
				.map((item) => {
					return `<div class="yamarket-basket-summary__row">
						<div class="yamarket-basket-summary__label">${item['NAME']}:</div>
						<div class="yamarket-basket-summary__value">${item['VALUE']}</div>
					</div>`;
				})
				.join('')}
		</div>`;
	}

	renderBoxes(boxes: Array) : void {
		this.boxCollection?.destroy();

		this.boxCollection = new Collection({
            name: this.options.name,
            messages: Field.messages,
            actions: this.options.actions,
            onChange: this.onChange,
        });
		this.boxCollection.render(boxes, this.payloadBasket, this._mode);
		this.boxCollection.refreshBasketMove();
		this.boxCollection.refreshBoxDelete();
		this.boxCollection.mount(this.el);
	}

	hasAction(type: string) : boolean {
		return this.options.actions.indexOf(type) !== -1;
	}
}
