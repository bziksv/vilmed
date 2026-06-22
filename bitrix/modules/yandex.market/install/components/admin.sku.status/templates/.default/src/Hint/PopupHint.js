import {Hint} from "./Hint";

const BX = window.BX;

export class PopupHint extends Hint {

	constructor(anchor: HTMLElement) {
		super();

		this.hint = new BX.PopupWindow({
			autoHide: true,
			draggable: false,
			closeByEsc: true,
			bindElement: anchor,
			content: 'holder',
			maxWidth: 500,
			angleLeftOffset: 20,
			angle: {
				position: 'top',
				offset: 20 + this.angleOffset(anchor),
			},
		});
	}

	angleOffset(anchor: HTMLElement) : number {
		const rating = anchor.querySelector('.ym-sku-status-rating');
		const defaultOffset = 15;

		if (rating == null) { return defaultOffset; }

		const ratingRect = rating.getBoundingClientRect();
		const anchorRect = anchor.getBoundingClientRect();

		return ratingRect.left - anchorRect.left + ((ratingRect.width || 0) / 2);
	}

	destroy() : void {
		this.hint.destroy();
	}

	container() : HTMLElement {
		return this.hint.contentContainer;
	}

	show() : void {
		this.hint.show();
	}

	showNow() : void {
		this.hint.show();
	}

}