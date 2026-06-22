import {Glossary} from "./Glossary";
import {MessagePopup} from "./MessagePopup";
import {View} from "./View";
import type {Locale} from "../Component/Locale";

const BX = window.BX;

export class OfferGrid extends View {

	constructor(businesses: Array, elementStatus: Object, locale: Locale, insideHint: boolean) {
		super();
		this.elementStatus = elementStatus;
		this.businesses = businesses;
		this.locale = locale;
		this.insideHint = insideHint;
	}

	handlePopupClick(viewport: HTMLElement, dir: boolean) : void {
		for (const link of viewport.querySelectorAll('[data-action="popup"]')) {
			link[dir ? 'addEventListener' : 'removeEventListener']('click', this.onPopupClick);
		}
	}

	handleSiblingsClick(viewport: HTMLElement, dir: boolean) : void {
		for (const link of viewport.querySelectorAll('[data-action="siblings"]')) {
			link[dir ? 'addEventListener' : 'removeEventListener']('click', this.onSiblingsClick);
		}
	}

	onPopupClick = (evt: Event) : void => {
		const link = evt.currentTarget;
		const offer = this.offer(+link.dataset.business, +link.dataset.offer);

		(new MessagePopup(offer['messages'], this.locale)).show();

		evt.preventDefault();
	}

	onSiblingsClick = (evt: Event) : void => {
		const link = evt.currentTarget;
		const parent = link.parentElement;
		const title = link.getAttribute('title');

		link.remove();
		parent.textContent = parent.textContent.trim() + ', ' + title;

		evt.preventDefault();
	}

	mount(viewport: HTMLElement) : void {
		viewport.innerHTML = this.render();

		this.handlePopupClick(viewport, true);
		this.handleSiblingsClick(viewport, true);
	}

	unmount(viewport: HTMLElement) : void {
		this.handlePopupClick(viewport, false);
		this.handleSiblingsClick(viewport, false);
	}

	render() : string {
		const partials = [];

		for (const business of this.businesses) {
			if (this.elementStatus['businesses'][business['id']] == null) { continue; }

			const businessStatus = this.elementStatus['businesses'][business['id']];
			let html = '';

			if (this.businesses.length > 1) {
				html += `<div class="ym-sku-status-business">
	                <div class="ym-sku-status-business__title">${business.name}</div>
	                ${this.locale.message('RATING_TITLE')}: ${Glossary.rating(businessStatus['rating'], this.locale)}
	            </div>`;
			} else if (!this.insideHint) {
				html += `<div class="ym-sku-status-business">
	                ${this.locale.message('RATING_TITLE')}: ${Glossary.rating(businessStatus['rating'], this.locale)}
				</div>`;
			}

			if (businessStatus.error != null) {
				html += `<div class="errortext">${businessStatus.error}</div>`;
			} else {
				html += this.renderOffers(business, businessStatus);
			}

			partials.push(html);
		}

		if (partials.length === 0) {
			return this.insideHint
				? this.locale.message('UNPLACED')
				: `${this.locale.message('RATING_TITLE')}: ${this.locale.message('UNPLACED')}`;
		}

		return partials.join('');
	}

	renderOffers(business: Object, businessStatus: Object): string {
		const showLimit = 5;
		const offersHtml = businessStatus['offers'].map((offer) => `<div class="ym-sku-status-offer">
			<div class="ym-sku-status-offer__title">
				${this.offerName(offer)}
				${this.offerSiblings(offer)}
			</div>
			${offer.caption != null ? `
				${offer.caption.text}<br />
				${offer.caption.more ? `${offer.caption.more}<br />` : ''}
				<a class="ym-sku-status-offer__more" href="#" data-business="${business['id']}" data-offer="${offer['id']}" data-action="popup">${this.locale.message('DETAILS')}</a>
			` : ''}
		</div>`);

		if (offersHtml.length <= showLimit) {
			return offersHtml.join('');
		}

		const commonOffersHtml = offersHtml.slice(0, showLimit);
		const additionalOffersHtml = offersHtml.slice(showLimit);

		return `
			${commonOffersHtml.join('')}
			<details class="ym-sku-status-offers-additional">
				<summary>${this.locale.message('MORE')} ${additionalOffersHtml.length}</summary>
				${additionalOffersHtml.join('')}
			</details>
		`;
	}

	offerSiblings(offer: Object) : string {
		const siblings = offer['siblings'];

		if (siblings == null) { return ''; }

		const names = siblings.map((offer) => this.offerName(offer)).join(', ');

		// noinspection JSUnresolvedReference
		return `<a class="ym-sku-status-offer__siblings" href="#" title="${BX.util.htmlspecialchars(names)}" data-action="siblings">
			${this.locale.message('MORE')} ${siblings.length}
		</a>`;
	}

	offerName(offer: Object) : string {
		return `${offer['name']} [${offer['sku']}]`;
	}

	offer(businessId: number, offerId: number) : Object {
		for (const business of this.businesses) {
			if (business['id'] !== businessId) { continue; }

			for (const offer of this.elementStatus['businesses'][business['id']]['offers']) {
				if (offer['id'] !== offerId) { continue; }

				return offer;
			}
		}

		throw new Error(`offer ${offerId} for business ${businessId} not found`);
	}

}