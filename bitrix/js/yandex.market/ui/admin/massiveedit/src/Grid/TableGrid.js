import {Grid} from "./Grid";

export class TableGrid extends Grid {

	getSelectedIds() : Array<string> {
		const result = [];
		const form = this.adminList().FORM;

		for (const checkbox of form.querySelectorAll('input[type="checkbox"][name="ID[]"]')) {
			if (checkbox.checked) {
				result.push(checkbox.value);
			}
		}

		return result;
	}

	isForAllChecked() : boolean {
		const actionTarget = this.adminList().ACTION_TARGET;

		return actionTarget && actionTarget.checked;
	}

	reload() : void {
		const adminList = this.adminList();
		const form = adminList.FORM;

		BX.showWait(form);
		adminList.GetAdminList(window.location.href, () => {
			BX.closeWait(form);
		});
	}

	showError(message: string) : void {
		alert(message);
	}

	adminList() {
		return window[this.tableId];
	}
}