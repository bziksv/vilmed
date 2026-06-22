import {Grid} from "./Grid";

export class UiGrid extends Grid {

	constructor(tableId: string) {
		super(tableId);
		this.grid = BX.Main.gridManager.getById(tableId).instance;
	}

	getSelectedIds() : Array {
		return this.grid.getRows().getSelectedIds();
	}

	isForAllChecked() : boolean {
		const panel = this.grid.getActionsPanel();

		if (panel == null) { return false; }

		const checkbox = panel.getForAllCheckbox();

		if (checkbox === null) { return false; }

		return checkbox.checked;
	}

	reload() : void {
		this.grid.reload();
	}

	showError(message: string) : void {
		this.grid.arParams.MESSAGES = [
			{ TYPE: 'ERROR', TEXT: message }
		];

		BX.onCustomEvent(window, 'BX.Main.grid:paramsUpdated', []);
	}
}