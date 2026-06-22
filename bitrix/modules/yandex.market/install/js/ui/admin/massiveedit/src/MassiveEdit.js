import {Factory as GridFactory} from "./Grid/Factory";
import {Grid} from "./Grid/Grid";

export class MassiveEdit {

	static defaults = {
		url: null,
		iblockId: null,
		prefixSelected: null,
		lang: {},
	};

	static open(tableType: string, tableId: string, options: Object = {}) : void {
		const grid = GridFactory.make(tableType, tableId);
		const form = new this(grid, options);

		form.show();
	}

	constructor(grid: Grid, options: Object = {}) {
		this.grid = grid;
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	show() : void {
		try {
			const modal = this.createModal();

			modal.Show();
			this.bind();
		} catch (error) {
			this.grid.showError(error.message);
		}
	}

	bind() : void {
		this.handleModalClose(true);
		this.handleActionDone(true);
	}

	unbind() : void {
		this.handleActionDone(false);
		this.handleModalClose(false);
	}

	handleModalClose(dir: boolean) : void {
		if (this._modal == null) { return; }

		BX[dir ? 'addCustomEvent' : 'removeCustomEvent'](this._modal, 'onWindowClose', this.onWindowClose);
	}

	handleActionDone(dir: boolean) : void {
		BX[dir ? 'addCustomEvent' : 'removeCustomEvent']('onYandexMarketMassiveEditDone', this.onActionDone);
	}

	onWindowClose = () => {
		this.unbind();
		this.releaseModal();
	}

	onActionDone = () => {
		this.unbind();
		this.closeModal();
		this.grid.reload();
	}

	releaseModal(): void {
		this._modal = null;
	}

	closeModal() : void {
		if (this._modal == null) { return; }

		BX.closeWait();
		this._modal.Close();
		this._modal = null;
	}

	createModal() : BX.CAdminDialog {
		if (this._modal == null) {
			this._modal = new BX.CAdminDialog({
				title: this.options.lang['MODAL_TITLE'],
				content_url: this.options.url,
				content_post: {
					SELECTED: this.selectedRows(),
					IBLOCK_ID: this.options.iblockId,
				},
				width: 720,
				height: 800,
				resizable: true,
				buttons: [
					BX.CAdminDialog.btnSave,
					BX.CAdminDialog.btnCancel,
				],
			});
		}

		return this._modal;
	}

	selectedRows() : Array {
		if (this.grid.isForAllChecked()) {
			throw new Error(this.options.lang['FOR_ALL_NOT_SUPPORTED']);
		}

		const selected = this.grid.getSelectedIds();
		const prefix = this.options.prefixSelected;

		if (prefix != null) {
			return selected.map((id) => `${prefix}${id}`);
		}

		return selected;
	}
}