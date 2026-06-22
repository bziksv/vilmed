import {View} from "./View";

export class FailView extends View {

	constructor(error: Error, locale: Locale) {
		super();
		this.error = error;
		this.locale = locale;
	}

	mount(viewport: HTMLElement) {
		const loader = viewport.querySelector('.ym-sku-status-loader');
		const html = `<div class="errortext">${this.error.message || this.locale.message('UNKNOWN_ERROR')}</div>`;

		if (loader != null) {
			loader.outerHTML = html;
		} else {
			viewport.innerHTML = html;
		}
	}

}