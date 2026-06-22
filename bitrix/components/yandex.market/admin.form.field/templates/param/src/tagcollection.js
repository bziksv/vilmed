import {TagFactory} from "./tagfactory";
import {Tag} from "./tag";

const $ = window.YMarketJQuery || window.jQuery;
const BX = window.BX;
const Reference = BX.namespace('YandexMarket.Field.Reference');

export class TagCollection extends Reference.Collection {

	static dataName = 'FieldParamTagCollection';
	static defaults = Object.assign({}, Reference.Collection.prototype.defaults, {
		titleElement: '.js-param-tag-collection__title',
		itemElement: '.js-param-tag-collection__item',
		itemDeleteElement: '.js-param-tag-collection__item-delete',
		factoryElement: '.js-param-tag-collection__factory.level--0',
		factory: null,
	});

	_factory;
	_factoryIndex;

	initialize() {
		super.initialize();
		this.initializeFactory();
		this.bind();
	}

	destroy() {
		this.unbind();
		this.destroyFactory();
		super.destroy();
	}

	bind() {
		this.handleItemDeleteClick(true);
	}

	unbind() {
		this.handleItemDeleteClick(false);
	}

	handleItemDeleteClick(dir) {
		const itemDeleteSelector = this.getElementSelector('itemDelete');

		this.$el[dir ? 'on' : 'off']('click', itemDeleteSelector, $.proxy(this.onItemDeleteClick, this));
	}

	onItemDeleteClick(evt) {
		const deleteButton = $(evt.currentTarget);
		const item = this.getElement('item', deleteButton, 'closest');

		this.deleteItem(item);

		evt.preventDefault();
	}

	addTag(type) {
		const typeCollection = this.getTypeCollection(type, true);
		const item = typeCollection.eq(-1);
		const wasHidden = item.hasClass('is--hidden');
		const newInstance = this.addItem(item);

		if (newInstance != null && wasHidden && typeCollection.length === 1) {
			newInstance.preselect();
		}
	}

	addItem(source, context, method) {
		const sourceItem = source || this.getElement('item').eq(-1);
		const isMultiple = sourceItem.data('multiple');
		const isRequired = sourceItem.data('required');
		const itemType = sourceItem.data('type');

		if (itemType) {
			const typeCollection = this.getTypeCollection(itemType);

			if (!isMultiple && typeCollection.length > 0) {
				return null;
			}

			if (isRequired && typeCollection.length === 1) {
				this.toggleItemDeleteView(true, typeCollection);
			}

			if (!isMultiple && typeCollection.length === 0 && this._factory != null) { // can't add more
				this._factory.hideItem(itemType, this._factoryIndex);
			}
		}

		this.toggleTitle(true);

		return super.addItem(sourceItem, context, method);
	}

	deleteItem(item) {
		const isRequired = item.data('required');
		const isPersistent = item.data('persistent');
		const itemType = item.data('type');

		if (itemType) {
			const typeCollection = this.getTypeCollection(itemType);

			if ((isRequired || isPersistent) && typeCollection.length === 2) {
				this.toggleItemDeleteView(false, typeCollection);
			} else if (typeCollection.length === 1) {
				if (isRequired || isPersistent) {
					this.clearItem(item);
				} else {
					if (this._factory != null) {
						this._factory.showItem(itemType, this._factoryIndex);
					}

					item.addClass('is--hidden');
					this.destroyItem(item, true);

					if (this.getActiveItems().length === 0) {
						this.toggleTitle(false);
					}
				}

				return;
			} else if (typeCollection.length === 0) {
				return;
			}
		}

		super.deleteItem(item);
	}

	toggleItemDeleteView(dir, context) {
		this.getElement('itemDelete', context).toggleClass('is--hidden', !dir);
	}

	getTypeCollection(type, isIncludePlaceholder) {
		return this.getElement('item').filter(function(index, node) {
			const element = $(node);

			return (
				(element.data('type') === type)
				&& (isIncludePlaceholder || !element.hasClass('is--hidden'))
			);
		});
	}

	toggleTitle(dir: boolean) : void {
		this.getElement('title').toggleClass('is--hidden', !dir);
	}

	setFactory(factory: TagFactory, index: number) : void {
		this._factory = factory;
		this._factoryIndex = index;
	}

	initializeFactory() {
		const element = this.getElement('factory');

		if (element.length === 0) { return; }

		this._factory = new TagFactory(element, {
			collection: this,
		});
	}

	destroyFactory() {
		if (this._factory == null || this._factoryIndex != null) { return; }

		this._factory.destroy();
		this._factory = null;
	}

	enable() : void {
		this.callItemList('enable');
	}

	disable() : void {
		this.callItemList('disable');
	}

	getItemPlugin() {
		return Tag;
	}

}