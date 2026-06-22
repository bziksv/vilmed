import {TagFactory} from "./tagfactory";

export class TagGroupFactory extends TagFactory {

    static defaults = Object.assign({}, TagFactory.defaults, {
        groups: [],
	    groupFlat: null,
    });

	direct(): Array {
		if (this.options.groups.length > 1) { return []; }

		const group = this.options.groups[0];
		const direct = super.direct(group['TAG_FACTORY']);

		if (direct[0] == null) { return []; }

		return [ direct[0], 0 ];
	}

	dropdownMenu() : Array {
        const result = [];

        for (let index = 0; index < this.options.groups.length; ++index) {
            const group = this.options.groups[index];
            const items = this.dropdownItems(group['TAG_FACTORY'], index);

            if (items.length === 0) { continue; }

            if (!group['TITLE']) {
	            result.push(...items);
            } else if (this.options.groupFlat) {
	            result.push(...items.map((item: Object) : Object => {
					if (items.length === 1) {
						item['TEXT'] = group['TITLE'];
						item['HTML'] = group['TITLE'];
					} else {
						item['TEXT'] = `${group['TITLE']}: ${item['TEXT']}`;
						item['HTML'] = `${group['TITLE']}: ${item['HTML']}`;
					}

		            return item;
	            }));
            } else {
	            result.push({
		            TEXT: group['TITLE'],
		            HTML: group['TITLE'],
		            MENU: items,
	            });
            }
        }

        return result;
    }

    toggleItem(type: string, group: ?number, dir: boolean) : void {
        this.options.groups[group]['TAG_FACTORY'][type]['ENABLED'] = dir;
    }

    hasActiveItems() : boolean {
        for (const group of this.options.groups) {
            for (const [, state] of Object.entries(group['TAG_FACTORY'])) {
                if (state['ENABLED']) {
                    return true;
                }
            }
        }

        return false;
    }

}