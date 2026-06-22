import {UiGrid} from "./UiGrid";
import {TableGrid} from "./TableGrid";
import type {Grid} from "./Grid";

export class Factory {
	static make(tableType: string, tableId: string) : Grid {
		if (tableType === 'Ui') {
			return new UiGrid(tableId);
		}

		return new TableGrid(tableId);
	}
}