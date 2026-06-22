import {Hint} from "./Hint";
import {AdminHint} from "./AdminHint";
import {PopupHint} from "./PopupHint";

export class HintFactory {

	static create(anchor: HTMLElement, admin: boolean) : Hint {
		return admin ? new AdminHint(anchor) : new PopupHint(anchor);
	}

}