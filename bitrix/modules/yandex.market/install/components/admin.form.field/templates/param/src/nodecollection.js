import {TagCollection} from "./tagcollection";
import {Node} from "./node";

export class NodeCollection extends TagCollection {

	static dataName = 'FieldParamNodeCollection';
	static defaults = Object.assign({}, TagCollection.defaults, {
		itemElement: '.js-param-node-collection__item',
		itemDeleteElement: '.js-param-node-collection__item-delete',
		factoryElement: '.js-param-node-collection__attribute-factory',
	});

	bind() {
		super.bind();
		this.handleLinkedChange(true);
	}

	unbind() {
		this.handleLinkedChange(false);
		super.unbind();
	}

	handleLinkedChange(dir) {
		this.$el[dir ? 'on' : 'off']('FieldParamNodeLinkedChange', $.proxy(this.onLinkedChange, this));
	}

	onLinkedChange(evt, data) {
		this.callItemList(function(itemInstance) {
			if (itemInstance === data.node) { return; }

			itemInstance.syncLinked(data.source, data.field);
		});
	}

	toggleItemDeleteView() {
		// nothing
	}

	preselect() {
		this.callItemList('preselect');
	}

	getItemPlugin() {
		return Node;
	}

}