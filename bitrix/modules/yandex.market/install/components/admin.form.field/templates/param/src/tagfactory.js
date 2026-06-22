import type {TagCollection} from "./tagcollection";
import type {TagGroup} from "./taggroup";

const $ = window.YMarketJQuery || window.jQuery;
const BX = top.BX;
const Plugin = BX.namespace('YandexMarket.Plugin');

export class TagFactory extends Plugin.Base {

    static defaults = Object.assign({}, Plugin.Base.prototype.defaults, {
        collection: null,
        items: {},
    });

    _valid = false;

    initialize() : void {
        super.initialize();
        this.bind();
    }

    destroy() : void {
        this.unbind();
        super.destroy();
    }

    bind() : void {
        this.handleOpenClick(true);
    }

    unbind() : void {
        this.handleOpenClick(false);
    }

    handleOpenClick(dir: boolean) : void {
        this.$el[dir ? 'on' : 'off']('click', $.proxy(this.onOpenClick, this));
    }

    onOpenClick(evt) : void {
		const [direct, group] = this.direct();

		if (direct != null) {
			this.collection().addTag(direct, group);
		} else {
			this.open();
		}

        evt.preventDefault();
    }

	direct() : Array {
		const entries = Object.entries(this.options.items);

		if (entries.length !== 1) { return []; }

		return [ entries[0][0], null ];
	}

    collection() : TagCollection|TagGroup {
        return this.options.collection;
    }

    open() : void {
        if (this.el.OPENER == null) {
            this._valid = true;
            BX.adminShowMenu(this.el, this.dropdownMenu(), {
                active_class: this.el.classList.contains('adm-btn') ? 'adm-btn-active' : '',
            });

            return;
        }

        if (!this._valid) {
            this.el.OPENER.SetMenu(this.dropdownMenu());
        }
    }

    dropdownMenu() : Array {
        return this.dropdownItems(this.options.items);
    }

    dropdownItems(tagsMap: Object, group: number = null) : Array {
        const result = [];

        for (const [tagId, state] of Object.entries(tagsMap)) {
            if (!state['ENABLED']) { continue; }

            result.push({
                TEXT: state['TITLE'],
                HTML: state['TITLE'],
                ONCLICK: () => { this.collection().addTag(tagId, group); },
            });
        }

        return result;
    }

    showItem(type: string, group: ?number = null) : void {
        this._valid = false;
        this.toggleItem(type, group, true);
        this.toggleVisible(true);
    }

    hideItem(type: string, group: ?number = null) : void {
        this.toggleItem(type, group, false);
        this._valid = false;
        !this.hasActiveItems() && this.toggleVisible(false);
    }

    toggleItem(type: string, group: ?number, dir: boolean) : void {
        this.options.items[type]['ENABLED'] = dir;
    }

    hasActiveItems() : boolean {
        for (const [, state] of Object.entries(this.options.items)) {
            if (state['ENABLED']) {
                return true;
            }
        }

        return false;
    }

    toggleVisible(dir: boolean) : void {
        this.$el.toggleClass('is--hidden', !dir);
    }

}