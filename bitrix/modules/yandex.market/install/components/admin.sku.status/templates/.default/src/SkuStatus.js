import type {Repository} from "./Component/Repository";
import type {Locale} from "./Component/Locale";
import type {View} from "./View/View";
import {SummaryRow} from "./View/SummaryRow";
import {OfferGrid} from "./View/OfferGrid";
import {FailView} from "./View/FailView";

export class SkuStatus {

	static defaults = {
		id: null,
		theme: null,
		admin: false,
	};

	destroyed: boolean = false;

	constructor(element: HTMLElement, transport: Repository, locale: Locale, options = {}) {
		this.el = element;
		this.transport = transport;
		this.locale = locale;
		this.options = Object.assign({}, SkuStatus.defaults, options);
	}

	destroy() : void {
		this.destroyed = true;
		this.transport = null;
		this.locale = null;

		this.destroyView();
	}

	id() : number {
		return this.options.id;
	}

	load() : void {
		this.loading();

		this.transport.load(this.options.id)
			.then(this.render.bind(this))
			.catch(this.fail.bind(this));
	}

	waitLoad(promise: Promise) : void {
		promise
			.then(this.render.bind(this))
			.catch(this.fail.bind(this));
	}

	loading() : void {
		const loader = this.el.querySelector('.ym-sku-status-loader');

		if (loader == null) { return; }

		loader.classList.add('is--active');
	}

	render(response: Object) : void {
		const elementStatus = response.element;
		const businesses = response.businesses;

		if (this.destroyed) { return; }

		this.draw(
			this.options.theme === 'grid'
				? new SummaryRow(businesses, elementStatus, this.locale, this.options.admin)
				: new OfferGrid(businesses, elementStatus, this.locale)
		);
	}

	fail(error: Error) : void {
		if (this.destroyed) { return; }

		this.draw(new FailView(error, this.locale));
	}

	draw(view: View) : void {
		this.destroyView();

		this._view = view;
		view.mount(this.el);
	}

	destroyView() : void {
		if (this._view == null) { return; }

		this._view.unmount(this.el);
		this._view = null;
	}
}
