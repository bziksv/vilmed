import {CategoryPanel} from "./CategoryPanel";

const BX = window.BX;

// noinspection JSUnusedGlobalSymbols
export class CategoryFactory {
	static READY_TOKEN = 'ym-category--ready';

	constructor(className: string, options: Object = {}) {
		this.className = className;
		this.options = options;
		this.instances = [];

		this.boot();
		this.bind();
	}

	boot() : void {
		for (const element of document.getElementsByClassName(this.className)) {
			if (element.classList.contains(CategoryFactory.READY_TOKEN)) { continue; }

			this.instances.push(new CategoryPanel(element, this.options));

			element.classList.add(CategoryFactory.READY_TOKEN);
		}
	}

	bind() : void {
		// noinspection JSUnresolvedReference
		BX.addCustomEvent('onAdminTabsChange', () => this.check());
		BX.addCustomEvent('Grid::updated', () => this.check());
		BX.addCustomEvent('Grid::noEditedRows', () => this.check());
	}

	check() : void {
		for (const instance of this.instances.slice()) {
			if (document.contains(instance.el)) { continue; }

			instance.destroy();
			this.instances.splice(this.instances.indexOf(instance), 1);
		}
	}
}