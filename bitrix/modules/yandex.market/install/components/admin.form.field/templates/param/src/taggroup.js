import {TagFactory} from "./tagfactory";
import {TagCollection} from "./tagcollection";
import {TagGroupFactory} from "./taggroupfactory";

// noinspection JSUnusedLocalSymbols
const $ = window.YMarketJQuery || window.jQuery;
const BX = window.BX;
const Plugin = BX.namespace('YandexMarket.Plugin');

export class TagGroup extends Plugin.Base {

    static dataName = 'FieldParamTagCollectionGroup';
    static defaults = Object.assign({}, Plugin.Base.prototype.defaults, {
        addElement: '.js-param-tag-group__factory',
        itemElement: '.js-param-tag-group__item',
    });

    _factory;
    _tagCollections;

    initialize() : void {
        super.initialize();
        this.initializeFactory();
        this.initializeTagCollection();
    }

    destroy() : void {
        this.destroyTagCollection();
        this.destroyFactory();

        super.destroy();
    }

    initializeFactory() : void {
        this._factory = new TagGroupFactory(this.getElement('add'), {
            collection: this,
        });
    }

    destroyFactory() : void {
        if (this._factory == null) { return; }

        this._factory.destroy();
        this._factory = null;
    }

    initializeTagCollection() : void {
        const collectionElements = this.getElement('item');

        this._tagCollections = [];

        for (let i = 0; i < collectionElements.length; ++i) {
            const tagCollection = TagCollection.getInstance(collectionElements[i]);
            tagCollection.setFactory(this._factory, i);

            this._tagCollections[i] = tagCollection;
        }
    }

    destroyTagCollection() : void {
        if (this._tagCollections == null) { return; }

        for (const tagCollection of this._tagCollections) {
            tagCollection.destroy();
        }

        this._tagCollections = null;
    }

    addTag(type: string, index: number) : void {
        this._tagCollections[index].addTag(type);
    }

    factory() : TagFactory {
        return this._factory;
    }

	enable() : void {
		for (const tagCollection of this._tagCollections) {
			tagCollection.enable();
		}
	}

	disable() : void {
		for (const tagCollection of this._tagCollections) {
			tagCollection.disable();
		}
	}

}