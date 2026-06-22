import {Glossary} from "./Glossary";
import {OfferGrid} from "./OfferGrid";
import {View} from "./View";
import type {Hint} from "../Hint/Hint";
import {HintFactory} from "../Hint/HintFactory";

export class SummaryRow extends View {

	viewport: HTMLElement;
	hint: ?Hint;
	admin: boolean;

	defaults = {
		showTimeout: 200,
		hideTimeout: 200
	};

	constructor(businesses: Array, elementStatus: Object, locale: Locale, admin: boolean) {
		super();

		this.elementStatus = elementStatus;
		this.businesses = businesses;
		this.locale = locale;
		this.offerGrid = new OfferGrid(businesses, elementStatus, locale, true);
		this.admin = admin;
	}

	handleClick(viewport: HTMLElement, dir: boolean) : void {
		viewport[dir ? 'addEventListener' : 'removeEventListener']('click', this.onClick);
	}

	handleMouseOver(viewport: HTMLElement, dir: boolean) : void {
		if (!this.admin) { return; }

		viewport[dir ? 'addEventListener' : 'removeEventListener']('mouseover', this.onMouseOver);
	}

	onClick = (evt: Event) : void => {
		this.handleMouseOver(evt.currentTarget, false);
		this.bootHint(evt.currentTarget).showNow();
	}

	onMouseOver = (evt) : void => {
		this.handleMouseOver(evt.currentTarget, false);
		this.bootHint(evt.currentTarget).show();
	}

	mount(viewport: HTMLElement) : void {
		viewport.innerHTML = `${this.locale.message('RATING_TITLE')}: ${Glossary.rating(this.elementStatus['rating'], this.locale)}`;

		this.handleClick(viewport, true);
		this.handleMouseOver(viewport, true);
	}

	unmount(viewport: HTMLElement) : void {
		this.handleMouseOver(viewport, false);
		this.handleClick(viewport, false);
		this.destroyHint();
	}

	bootHint(anchor: HTMLElement) : Hint {
		if (this.hint != null) { return this.hint; }

		this.hint = HintFactory.create(anchor, this.admin);
		this.offerGrid.mount(this.hint.container());

		return this.hint;
	}

	destroyHint() : void {
		if (this.hint == null) { return; }

		this.offerGrid.unmount(this.hint.container());
		this.hint.destroy();
		this.hint = null;
	}

}