import {BasketItemSkeletonModal} from "./basketitemskeletonmodal";

const $ = window.jQuery;

const constructor = BasketItemSkeletonModal.extend({

	defaults: {
		count: 1,
		countElement: 'input[name="MOVE_COUNT"]',
		boxElement: 'select[name="MOVE_BOX"]',

		boxCollection: null,
		currentIndex: null,
		selectedIndex: null,

		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_MOVE_',
		lang: {},
	},

	afterOpen: function(modal) {
		const content = this.modalContent(modal);

		this.renderCount(content);
		this.renderBox(content);
	},

	renderCount: function(content) {
		const input = this.getElement('count', content);

		input.val(this.options.count);
		input.prop('max', this.options.count);
	},

	renderBox: function(content) {
		const select = this.getElement('box', content);
		const boxes = this.options.boxCollection.getItemInstances();

		for (let i = 0; i < boxes.length; i++) {
			const box = boxes[i];
			const boxIndex = box.getIndex() || 0;

			if (boxIndex === this.options.currentIndex || box.onlyForPart()) { continue; }

			const option = $(`<option value="${boxIndex + 1}">${box.getTitle()}</option>`);

			option.prependTo(select);
			option.prop('selected', boxIndex === this.options.selectedIndex);
		}

		if (this.options.selectedIndex == null) {
			select.find('option').eq(-1).prop('selected', true);
		}
	},

	resolveValues: function(modal) {
		const content = this.modalContent(modal);

		return [
			this.resolvedBox(content),
			this.resolvedCount(content),
		];
	},

	resolvedBox: function(content) {
		const select = this.getElement('box', content);
		const selected = parseInt(select.val()) || 0;

		if (selected === 0) { return this.options.boxCollection.createNew(); }

		const boxElement = this.options.boxCollection.getItem(selected - 1);

		if (boxElement == null) { throw new Error(`cant find box with index ${selected}`); }

		return this.options.boxCollection.getItemInstance(boxElement);
	},

	resolvedCount: function(content) {
		const input = this.getElement('count', content);

		return parseInt(input.val()) || 1;
	},

});

export const BasketItemMoveModal = constructor;