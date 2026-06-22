import {Box} from "./box";

const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');
const Plugin = BX.namespace('YandexMarket.Plugin');

const constructor = Reference.Collection.extend({

	defaults: {
		itemElement: '.js-yamarket-box',
		footerElement: 'tfoot',
	},

	getCountChanges: function() {
		let result = [];

		this.callItemList(function(box) {
			result = result.concat(box.getBasket().getCountChanges());
		});

		return result;
	},

	getPreviousMovableBox: function(box) {
		let previous;
		let result;

		this.callItemList(function(item) {
			if (item === box) {
				result = previous;
			}

			if (!item.onlyForPart()) {
				previous = item;
			}
		});

		return result;
	},

	getNextMovableBox: function(box) {
		let found = false;
		let result;

		this.callItemList(function(item) {
			if (found && result == null && !item.onlyForPart()) {
				result = item;
			}

			if (item === box) {
				found = true;
			}
		});

		return result;
	},

	createNew: function() {
		return this.addItem();
	},

	sameBasketItems: function(basketItem) {
		let result = [];

		this.callItemList(function(box) {
			result = result.concat(box.getBasket().sameBasketItems(basketItem));
		});

		return result;
	},

	validate: function() {
		this.callItemList(function(box) {
			try {
				box.validate()
			} catch (e) {
				const title = box.getTitle();
				const message = (title ? title + ': ' : '') + e.message;

				throw new Error(message);
			}
		});
	},

	addItem: function(source, context, method, isCopy) {
		const result = this.callParent('addItem', [source, context, method, isCopy], constructor);

		this.refreshBoxNumber();
		this.refreshBoxDelete();

		return result;
	},

	detachItem: function(item) {
		const itemSelector = this.getElementSelector('item');
		const footerSelector = this.getElementSelector('footer');
		const siblings = item.nextUntil([itemSelector, footerSelector].join(', '));

		this.callParent('detachItem', [item.add(siblings)], constructor);
	},

	cloneItem: function(sourceItem) {
		const itemSelector = this.getElementSelector('item');
		const footerSelector = this.getElementSelector('footer');
		const siblings = sourceItem.nextUntil([itemSelector, footerSelector].join(', '));

		return sourceItem.clone(false, false).add(siblings.clone(false, false));
	},

	appendItem: function(item, context, method) {
		if (method === 'after') {
			const itemSelector = this.getElementSelector('item');
			const footerSelector = this.getElementSelector('footer');
			const next = context.nextAll([itemSelector, footerSelector].join(', '));

			if (next.length > 0) {
				context = next.eq(0);
				method = 'before';
			} else {
				context = context.parent();
				method = 'append';
			}
		}

		context[method](item);
		Plugin.manager.initializeContext(item);
	},

	deleteItem: function(item, silent) {
		this.transferBasket(item);
		this.callParent('deleteItem', [item, silent], constructor);
		this.refreshBoxNumber();
		this.refreshBoxDelete();
	},

	transferBasket: function(item) {
		const box = this.getItemInstance(item);
		const basket = box.getBasket();

		if (basket.getActiveItems().length === 0) { return; }

		const siblingBox = this.getPreviousMovableBox(box) ?? this.getNextMovableBox(box);

		if (siblingBox == null) { throw new Error('cant delete last box'); }

		basket.callItemList('move', [siblingBox]);
	},

	initializeItem: function(item, index, sourceItem) {
		return this.callParent('initializeItem', [item.eq(0), index, sourceItem], constructor);
	},

	refreshBoxNumber: function() {
		this.callItemList('refreshNumber');
	},

	refreshBoxDelete: function() {
		const enabled = this.getActiveItems().length > 1;

		this.callItemList('refreshDelete', [enabled]);
	},

	getOrder: function() {
		return this.getParentField();
	},

	getItemPlugin: function() {
		return Box;
	}

}, {
	dataName: 'orderViewBoxCollection',
	pluginName: 'YandexMarket.OrderView.BoxCollection',
});

export const BoxCollection = constructor;