import {Hint} from "./Hint";

const BX = window.BX;

export class AdminHint extends Hint {

	defaults = {
		showTimeout: 200,
		hideTimeout: 200
	};

	constructor(anchor: HTMLElement) {
		super();

		this.hint = new BX.CHint({
			parent: anchor,
			hint: 'holder',
			show_timeout: this.defaults.showTimeout,
			hide_timeout: this.defaults.hideTimeout,
		});
		this.hint.Init();
	}

	destroy() : void {
		this.hint.Destroy();
	}

	container() : HTMLElement {
		return this.hint.CONTENT_TEXT;
	}

	show() : void {
		this.hint.Show();
	}

	showNow() : void {
		const originalTimeout = this.hint.PARAMS.show_timeout;

		this.hint.PARAMS.show_timeout = 0;
		this.hint.Show();
		this.hint.PARAMS.show_timeout = originalTimeout;
	}

}