import {BasketItemSkeletonModal} from "./basketitemskeletonmodal";

const constructor = BasketItemSkeletonModal.extend({

	defaults: {
		countElement: 'input[name="SPLIT_COUNT"]',

		langPrefix: 'YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_SPLIT_',
		lang: {},
	},

	resolveValues: function(modal) {
		const input = this.getElement('count', this.modalContent(modal));

		return parseInt(input.val().trim()) || 1;
	},

});

export const BasketItemSplitModal = constructor;