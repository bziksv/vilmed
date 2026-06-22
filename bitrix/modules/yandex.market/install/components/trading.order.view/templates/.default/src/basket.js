import { BasketItem } from "./basketitem";

const BX = window.BX;
const FieldReference = BX.namespace('YandexMarket.Field.Reference');

const constructor = FieldReference.Collection.extend({

	defaults: {
		itemElement: '.js-yamarket-basket-item'
	},

	insertItem: function(itemElement) {
		itemElement.detach();
		itemElement.addClass('is--hidden');
		itemElement.appendTo(this.$el);

		return this.addItem(itemElement, null, null, true);
	},

	sameBasketItems: function(target) {
		const targetId = target.id();
		const result = [];

		this.callItemList((item) => {
			if (item.id() === targetId) {
				result.push(item);
			}
		});

		return result;
	},

	addItem: function(source, context, method, isCopy) {
		const item = this.callParent('addItem', [source, context, method, isCopy], constructor);

		this.refreshItemMove();

		return item;
	},

	deleteItem: function(item, silent) {
		this.callParent('deleteItem', [item, silent], constructor);
		this.refreshItemMove();
	},

	refreshItemMove: function() {
		const box = this.getBox();
		const previousBox = box.getCollection().getPreviousMovableBox(box);
		const nextBox = box.getCollection().getNextMovableBox(box);
		const hasPrevious = previousBox != null && previousBox.getBasket().getActiveItems().length > 0;
		const hasNext = nextBox != null && nextBox.getBasket().getActiveItems().length > 0;
		const hasFewItems = this.getActiveItems().length > 1;

		this.callItemList('refreshMoveUp', [hasPrevious]);
		this.callItemList('refreshMoveDown', [hasNext || hasFewItems]);
	},

	validate: function() {
		this.callItemList((basketItem) => {
			try {
				basketItem.validate()
			} catch (e) {
				const title = basketItem.getTitle();
				const message = (title ? title + ': ' : '') + e.message;

				throw new Error(message);
			}
		});
	},

	commit: function(boxIndex) {
		let hasChanges = false;
		let activeCount = 0;

		this.callItemList((basketItem) => {
			let count = basketItem.getCount();

			if (count <= 0 || basketItem.needDelete()) {
				hasChanges = true;
				this.deleteItem(basketItem.$el);
				return;
			}

			if (basketItem.commit(boxIndex)) {
				hasChanges = true;
			}

			++activeCount;
		});

		if (activeCount === 0) {
			const box = this.getBox();
			box.getCollection().deleteItem(box.$el);
		}

		return hasChanges;
	},

	getCountChanges: function() {
		const result = [];

		this.callItemList((basketItem) => {
			let diff = basketItem.getCountDiff();

			if (diff <= 0 || (basketItem.isPart() && !basketItem.isFinalPart())) { return; }

			result.push({
				name: basketItem.getTitle(),
				diff: diff,
			});
		});

		return result;
	},

	getItemPlugin: function() {
		return BasketItem;
	},

	getBox: function() {
		return this.getParentField();
	}

}, {
	dataName: 'orderViewBasket',
	pluginName: 'YandexMarket.OrderView.Basket',
});

export const Basket = constructor;