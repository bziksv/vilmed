import type {TagSetting} from "./tagsetting";

export class TagSettingRegistry {
	static STATE_MAIN = 'main';
	static STATE_SHADOW = 'shadow';

	static groups = {};
	static groupsMain = {};

	static push(name: string, setting: TagSetting) : void {
		if (this.groups[name] == null) { this.groups[name] = []; }

		if (this.groupsMain[name] == null && setting.enabled()) {
			setting.groupMain();
			this.groupsMain[name] = setting;
		} else {
			setting.groupShadow();

			if (this.groupsMain[name] != null) {
				setting.setValue(this.groupsMain[name].getValue());
			}
		}

		this.groups[name].push(setting);
	}

	static shift(name: string, setting: TagSetting) : void {
		if (this.groups[name] == null) { return; }

		const group = this.groups[name];
		const index = group.indexOf(setting);

		if (index === -1) { return; }

		group.splice(index, 1);

		if (this.groupsMain[name] === setting) {
			setting.destroyGroupMain();
			this.groupsMain[name] = this._searchNewMain(name);
		}
	}

	static sync(name: string, setting: TagSetting) : void {
		if (this.groups[name] == null) { return; }

		const value = setting.getValue();

		for (const sibling of this.groups[name]) {
			if (sibling === setting) { continue; }

			sibling.setValue(value);
		}
	}

	static enable(name: string, setting: TagSetting) : void {
		if (this.groupsMain[name] != null) { return; }

		setting.groupMain();
		this.groupsMain[name] = setting;
	}

	static disable(name: string, setting: TagSetting) : void {
		if (this.groupsMain[name] !== setting) { return; }

		setting.destroyGroupMain();
		setting.groupShadow();
		this.groupsMain[name] = this._searchNewMain(name);
	}

	static _searchNewMain(name: string, skip: TagSetting = null) : ?TagSetting {
		for (const sibling of this.groups[name]) {
			if (sibling === skip || !sibling.enabled()) { continue; }

			sibling.groupMain();
			return sibling;
		}

		return null;
	}

}