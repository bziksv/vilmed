export class Grid {

	constructor(tableId: string) {
		this.tableId = tableId;
	}

	getSelectedIds() : Array<string> {}

	isForAllChecked() : boolean {}

	reload() : void {}

	showError(message: string) : void {}
}