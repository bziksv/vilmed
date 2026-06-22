import {SkuStatus} from "./SkuStatus";
import {Repository} from "./Component/Repository";
import {Locale} from "./Component/Locale";

const BX = (window.BX || top.BX);

export class SkuStatusFactory {
	static WAIT_TOKEN = 'wait';
	static READY_TOKEN = 'ready';

	static factories = {};
	static defaults = {
		transport: null,
		locale: {},
	};

	checkTimeout: ?number;

	static instance(selector: string, options: Object = {}) : SkuStatusFactory {
		if (this.factories[selector] == null) {
			this.factories[selector] = new this(selector, options);
		}

		return this.factories[selector];
	}

	constructor(selector: string, options: Object = {}) {
		this.selector = selector;
		this.options = Object.assign({}, SkuStatusFactory.defaults, options);
		this.observer = new IntersectionObserver(this.onIntersect.bind(this));
		this.locale = new Locale(this.options.locale);
		this.transport = new Repository(this, this.locale, this.options.transport);
		this.instances = new Map();

		this.search();
		this.bind();
	}

	onIntersect(entries: IntersectionObserverEntry[]) : void {
		for (const entry of entries) {
			if (!entry.isIntersecting) { continue; }

			this.make(entry.target).load();
			this.observer.unobserve(entry.target);
		}
	}

	search() : void {
		for (const element of document.querySelectorAll(this.selector)) {
			if (element.dataset.status != null) { continue; }

			this.observer.observe(element);
			element.dataset.status = SkuStatusFactory.WAIT_TOKEN;
		}
	}

	make(element: HTMLElement) : SkuStatus {
		const instance = new SkuStatus(element, this.transport, this.locale, Object.assign({}, this.options, {
			id: +element.dataset.id,
			theme: element.dataset.theme,
		}));

		element.dataset.status = SkuStatusFactory.READY_TOKEN;
		this.instances.set(element, instance);

		return instance;
	}

	bind() : void {
		BX.addCustomEvent('onAdminTabsChange', () => this.tryCheck());
		BX.addCustomEvent('onAjaxSuccessFinish', () => this.tryCheck());
		BX.addCustomEvent('Grid::updated', () => this.tryCheck());
		BX.addCustomEvent('Grid::thereEditedRows', () => this.tryCheck());
		BX.addCustomEvent('Grid::noEditedRows', () => this.tryCheck());
	}

	tryCheck() : void {
		clearTimeout(this.checkTimeout);
		this.checkTimeout = setTimeout(() => this.check(), 50);
	}

	check() : void {
		this.checkObserver();
		this.checkInstances();

		this.search();
	}

	checkObserver() : void {
		for (const entry of this.observer.takeRecords()) {
			if (document.contains(entry.target)) { continue; }

			this.observer.unobserve(entry.target);
		}
	}

	checkInstances() : void {
		for (const element of this.instances.keys()) {
			if (document.contains(element)) { continue; }

			this.instances.get(element).destroy();
			this.instances.delete(element);
		}
	}

	fulfilQueue(left: number, requested: number[], loaded: Object<number, Object>) : SkuStatus[] {
		if (left <= 0) { return requested; }

		const [ before, inside, after ] = this.splitElementsByPosition(requested, loaded);
		const instances = [];

		for (const element of this.selectNearestElements(left, before, inside, after)) {
			let instance;

			if (this.instances.has(element)) {
				instance = this.instances.get(element);
			} else {
				instance =  this.make(element);
				this.observer.unobserve(element);
			}

			instances.push(instance);
		}

		return instances;
	}

	splitElementsByPosition(requested: number[], loaded: Object<number, Object>) : HTMLElement[][] {
		const before = [];
		let inside = [];
		let after = [];
		let foundFirst = false;

		for (const element of document.querySelectorAll(this.selector)) {
			const id = +element.dataset.id;

			if (id <= 0 || Number.isNaN(id)) { continue; }

			if (requested.indexOf(id) !== -1) {
				foundFirst = true;

				if (after.length > 0) {
					inside.push(...after);
					after = [];
				}

				continue;
			}

			if (loaded[id] != null) { continue; }

			if (foundFirst) {
				after.push(element);
			} else {
				before.unshift(element);
			}
		}

		return [ before, inside, after ];
	}

	selectNearestElements(left: number, before: HTMLElement[], inside: HTMLElement[], after: HTMLElement[]) : HTMLElement[] {
		if (inside.length >= left) {
			return inside.slice(0, left);
		}

		const nearest = [];

		if (inside.length > 0) {
			nearest.push(...inside);
			left -= nearest.length;
		}

		const sides = [ before, after ].filter((side: HTMLElement[]) => side.length > 0);

		for (let i = left; i > 0; --i) {
			const side = sides.shift();

			if (side == null) { break; }

			nearest.push(side.shift());

			if (side.length === 0) { continue; }

			sides.push(side);
		}

		return nearest;
	}
}