import { BasketItemMoveModal } from "./basketitemmovemodal";
import { BasketItemSplitModal } from "./basketitemsplitmodal";

const BX = window.BX;
const $ = window.jQuery;
const FieldReference = BX.namespace('YandexMarket.Field.Reference');

const constructor = FieldReference.Complex.extend({

	defaults: {
		id: null,

		childElement: '.js-yamarket-basket-item__field',
		inputElement: '.js-yamarket-basket-item__data',

		upElement: '.js-yamarket-basket-item__up',
		moreElement: '.js-yamarket-basket-item__more',
		downElement: '.js-yamarket-basket-item__down',
		splitElement: '.js-yamarket-basket-item__split',
		splitModalElement: '#yamarket-basket-split-modal',
		moveModalElement: '#yamarket-basket-move-modal',

		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_',
		lang: {},
	},

	initVars: function() {
		this.callParent('initVars', constructor);
		this._moreDropdown = null;
	},

	initialize: function() {
		this.callParent('initialize', constructor);
		this.bind();
	},

	destroy: function() {
		this.unbind();
		this.callParent('destroy', constructor);
	},

	bind: function() {
		this.handleCountChange(true);
		this.handleUp(true);
		this.handleDown(true);
		this.handleMore(true);
	},

	unbind: function() {
		this.handleCountChange(false);
		this.handleUp(false);
		this.handleDown(false);
		this.handleMore(false);
	},

	handleCountChange: function(dir) {
		const input = this.getInput('COUNT');

		if (!input) { return; }

		input[dir ? 'on' : 'off']('change',  $.proxy(this.onCountChange, this));
	},

	handleUp: function(dir) {
		this.getElement('up')[dir ? 'on' : 'off']('click',  $.proxy(this.onUp, this));
	},

	handleDown: function(dir) {
		this.getElement('down')[dir ? 'on' : 'off']('click',  $.proxy(this.onDown, this));
	},

	handleMore: function(dir) {
		this.getElement('more')[dir ? 'on' : 'off']('click',  $.proxy(this.onMore, this));
	},

	onCountChange: function() {
		this.updateCisCount();
	},

	onUp: function() {
		const box = this.getBasket().getBox();
		const boxCollection = box.getCollection();

		this.moveStart(boxCollection, box, boxCollection.getPreviousMovableBox(box));
	},

	onDown: function() {
		const box = this.getBasket().getBox();
		const boxCollection = box.getCollection();

		this.moveStart(boxCollection, box, boxCollection.getNextMovableBox(box));
	},

	onMore: function(evt) {
		const menu = this.moreDropdown(evt.currentTarget);

		menu.setItems(this.moreMenuItems());
		menu.Show();
	},

	id: function() {
		return this.getInput('ID').val();
	},

	moveStart: function(boxCollection, currentBox, selectedBox) {
		const count = this.getCount();

		if (count <= 1) {
			const newItem = this.move(selectedBox ?? boxCollection.createNew());

			newItem.recalculateOffset();
			newItem.getBasket().refreshItemMove();

			return;
		}

		(new BasketItemMoveModal(this.getElement('moveModal'), {
			count: count,
			currentIndex: currentBox.getIndex(),
			selectedIndex: selectedBox != null ? selectedBox.getIndex() : null,
			boxCollection: boxCollection,
		}))
			.open()
			.then((resolved) => {
				const [box, moveCount] = resolved;
				let newItem;

				if (moveCount < count) {
					newItem = this.move(box, moveCount, true);

					this.setCount(this.getCount() - moveCount);
					this.setInitialCount(this.getInitialCount() - moveCount);
					this.recalculateOffset();
				} else {
					newItem = this.move(box);
					newItem.recalculateOffset();
				}

				newItem.getBasket().refreshItemMove();
			});
	},

	move: function(newBox, count, needClone) {
		const newBasket = newBox.getBasket();
		const sameItems = newBasket.sameBasketItems(this);
		const basket = this.getBasket();
		const box = basket.getBox();
		let newItem;

		if (sameItems.length > 0) {
			newItem = sameItems[0];

			if (count == null) { count = this.getCount(); }

			newItem.setCount(newItem.getCount() + count);
			newItem.setInitialCount(newItem.getInitialCount() + count);

			!needClone && basket.deleteItem(this.$el);
		} else {
			newItem = newBox.getBasket().insertItem(needClone ? this.$el.clone(false, false) : this.$el);

			if (count != null) {
				newItem.setCount(count);
				newItem.setInitialCount(count);
			}
		}

		if (basket.getActiveItems().length === 0) {
			box.getCollection().deleteItem(box.$el);
		} else {
			basket.refreshItemMove();
		}

		return newItem;
	},

	moreDropdown: function(anchor) {
		if (this._moreDropdown == null) {
			this._moreDropdown = new BX.CMenu({
				ATTACH_MODE: 'bottom',
				SET_ID: 'bx-admin-prefix',
				CLOSE_ON_CLICK: true,
				parent: anchor,
			});
		}

		return this._moreDropdown;
	},

	moreMenuItems: function() {
		const values = this.getValue();

		return (
			this.moreMenuItemsForSplit(values)
				.concat(this.moreMenuItemsForDelete(values))
		);
	},

	moreMenuItemsForDelete: function(values) {
		if (values['DELETE'] == null || values['PARTIAL_CURRENT']) { return []; }

		const result = [];

		if (values['DELETE']) {
			result.push({
				ONCLICK: $.proxy(this.markDeleted, this, false),
				TEXT: this.getLang('CANCEL_DELETE'),
			});
		} else {
			result.push({
				ONCLICK: $.proxy(this.markDeleted, this, true),
				TEXT: this.getLang('DELETE'),
			});
		}

		return result;
	},

	moreMenuItemsForSplit: function(values) {
		if (values['PARTIAL_CURRENT'] == null || values['DELETE']) { return []; }

		const result = [];

		if (values['PARTIAL_CURRENT']) {
			result.push({
				ONCLICK: $.proxy(this.cancelSplit, this),
				TEXT: this.getLang('CANCEL_SPLIT'),
			});
		} else {
			result.push({
				ONCLICK: $.proxy(this.splitStart, this),
				TEXT: this.getLang('SPLIT'),
			});
		}

		return result;
	},

	markDeleted: function(deleted) {
		this.getInput('DELETE').val(deleted ? 'Y' : '');
		this.$el.toggleClass('is--deleted', !!deleted);
	},

	cancelSplit: function() {
		const parts = this.getBasket().getBox().getCollection().sameBasketItems(this);
		const [count, initialCount] = this.countSiblings(parts);
		let first = true;

		for (const part of parts) {
			if (first) {
				const basket = part.getBasket();
				const box = basket.getBox();
				const previousBox = box.getCollection().getPreviousMovableBox(box) ?? box.getCollection().getNextMovableBox(box);

				part.resetPart();
				part.setCount(count);
				part.setInitialCount(initialCount);
				part.setOffset(0);
				part.updateCisCount();

				if (previousBox != null) {
					part.move(previousBox);
				} else {
					part.getBasket().refreshItemMove();
				}

				first = false;
				continue;
			}

			const basket = part.getBasket();
			const box = basket.getBox();

			basket.deleteItem(part.$el);

			if (basket.getActiveItems().length === 0) {
				box.getCollection().deleteItem(box.$el);
			}
		}
	},

	countSiblings: function(basketItems) {
		let count = 0;
		let initialCount = 0;

		for (const basketItem of basketItems) {
			if (!basketItem.isPart() || basketItem.isFinalPart()) {
				count += basketItem.getCount() || 0;
				initialCount += basketItem.getInitialCount() || 0;
			}
		}

		return [count, initialCount];
	},

	splitStart: function() {
		(new BasketItemSplitModal(this.getElement('splitModal')))
			.open()
			.then((total) => this.split(total));
	},

	split: function(total) {
		const basket = this.getBasket();
		const boxCollection = basket.getBox().getCollection();
		const [deletedCount, deletedInitialCount] = this.removeSiblings();
		const count = (this.getCount() || 1) + deletedCount;
		const initialCount = (this.getInitialCount() || 1) + deletedInitialCount;
		let lastItem;

		for (let productIndex = 0; productIndex < count; ++productIndex) {
			for (let i = 0; i < total; ++i) {
				const isSelf = (productIndex === 0 && i === 0);
				const newItem = this.move(boxCollection.createNew(), 1, !isSelf);

				newItem.setPart(i, total);

				if (productIndex === 0) {
					newItem.setInitialCount(1 + (initialCount - count));
				}

				lastItem = newItem;
			}
		}

		lastItem?.recalculateOffset();
	},

	removeSiblings() {
		const sameItems = this.getBasket().getBox().getCollection().sameBasketItems(this);
		let deletedCount = 0;
		let deletedInitialCount = 0;

		for (const sameItem of sameItems) {
			if (sameItem === this) { continue; }

			const basket = sameItem.getBasket();

			deletedCount += sameItem.getCount() || 0;
			deletedInitialCount += sameItem.getInitialCount() || 0;

			basket.deleteItem(sameItem.$el);

			if (basket.getActiveItems().length === 0) {
				const box = basket.getBox();
				box.getCollection().deleteItem(box.$el);
			}
		}

		return [deletedCount, deletedInitialCount];
	},

	resetPart: function() {
		const currentInput = this.getInput('PARTIAL_CURRENT');
		const totalInput = this.getInput('PARTIAL_TOTAL');
		const nameElement = this.getInput('PARTIAL_NAME');

		this.$el.removeClass('is--partial');
		currentInput.val('');
		totalInput.val('');
		nameElement.text('');
	},

	setPart: function(index, total) {
		const currentInput = this.getInput('PARTIAL_CURRENT');
		const totalInput = this.getInput('PARTIAL_TOTAL');
		const nameElement = this.getInput('PARTIAL_NAME');

		this.$el.addClass('is--partial');
		currentInput.val(index + 1);
		totalInput.val(total);
		nameElement.text(`${this.getLang('PART')} ${index + 1}/${total}`);
	},

	refreshMoveUp: function(canMoveUp) {
		this.getElement('up').prop('disabled', !canMoveUp || this.isPart());
	},

	refreshMoveDown: function(canMoveDown) {
		if (!canMoveDown && this.getCount() > 1) {
			canMoveDown = true;
		}

		this.getElement('down').prop('disabled', !canMoveDown || this.isPart());
	},

	updateCisCount: function() {
		const count = this.getCount();
		const offset = this.getOffset();
		const isValid = (count > 0);
		const fields = [
			this.getCis(),
			this.getDigital(),
		];

		if (!isValid) { return; }

		for (const field of fields) {
			if (field == null) { continue; }

			field.setBasketCount(count, offset);
			field.refreshSummary();
		}
	},

	isPart: function() {
		const input = this.getInput('PARTIAL_CURRENT');

		return input && !!(input.val());
	},

	isFinalPart: function() {
		const currentInput = this.getInput('PARTIAL_CURRENT');
		const currentValue = currentInput && parseInt(currentInput.val()) || 0;
		const totalInput = this.getInput('PARTIAL_TOTAL');
		const totalValue = totalInput && parseInt(totalInput.val()) || 0;

		return (currentValue > 0 && currentValue === totalValue);
	},

	commit: function(boxIndex) {
		const count = this.getCount();
		const initialCount = this.getInitialCount();
		const boxIndexInput = this.getInput('INITIAL_BOX');
		let changed = false;

		if (count !== initialCount) {
			changed = true;
			this.setInitialCount(count);
		}

		boxIndexInput && boxIndexInput.val(boxIndex);

		return changed;
	},

	getTitle: function() {
		const name = this.getInput('NAME');

		return name && name.text();
	},

	getCountDiff: function() {
		let result;

		if (this.needDelete()) {
			result = this.getInitialCount();
		} else {
			result = this.getInitialCount() - this.getCount();
		}

		return result;
	},

	setCount: function(count) {
		const input = this.getInput('COUNT');

		input.val(count);
	},

	getCount: function() {
		const input = this.getInput('COUNT');

		return input !== null ? parseInt(input.val()) : null;
	},

	recalculateOffset: function() {
		const sameItems = this.getBasket().getBox().getCollection().sameBasketItems(this);
		let offset = 0;

		for (const sameItem of sameItems) {
			sameItem.setOffset(offset);

			if (!sameItem.isPart() || sameItem.isFinalPart()) {
				offset += sameItem.getInitialCount();
			}

			sameItem.updateCisCount();
		}
	},

	setOffset: function(offset) {
		const input = this.getInput('OFFSET');

		input.val(offset || 0);
	},

	getOffset: function() {
		const input = this.getInput('OFFSET');

		return input !== null ? parseInt(input.val()) : null;
	},

	getInitialCount: function() {
		const input = this.getInput('INITIAL_COUNT');

		return input !== null ? parseInt(input.val()) : null;
	},

	setInitialCount: function(count) {
		const countInput = this.getInput('COUNT');
		const initialInput = this.getInput('INITIAL_COUNT');

		initialInput?.val(count);
		countInput?.prop('max', count);
	},

	needDelete: function() {
		const input = this.getInput('DELETE');

		return input && input.val() === 'Y';
	},

	validate: function() {
		if (this.needDelete()) { return; }

		const fields = [
			this.getCis(),
			this.getDigital(),
		];

		for (const field of fields) {
			field && field.validate();
		}
	},

	getCis: function() {
		return this.getChildField('IDENTIFIERS');
	},

	getDigital: function() {
		return this.getChildField('DIGITAL');
	},

	getBasket: function() {
		return this.getParentField();
	},

}, {
	dataName: 'orderViewBasketItem',
	pluginName: 'YandexMarket.OrderView.BasketItem',
});

export const BasketItem = constructor;