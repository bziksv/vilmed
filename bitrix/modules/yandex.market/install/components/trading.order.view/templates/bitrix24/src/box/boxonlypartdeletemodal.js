export default class BoxOnlyPartDeleteModal {

	static defaults = {
		messages: {},
	}

	constructor(options: Object = {}) {
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	open() : Promise {
		return new Promise((resolve, reject) => {
			const messageBox = BX.UI.Dialogs.MessageBox.create();

			messageBox.setMessage(this.getMessage('BOX_DELETE_PART_BY_MERGE'));
			messageBox.setButtons([
				new BX.UI.SaveButton({
					events: {
						click: () => {
							messageBox.close();
							resolve();
						},
					}
				}),
				new BX.UI.CancelButton({
					events: {
						click: () => {
							messageBox.close();
							reject();
						},
					}
				}),
			]);

			messageBox.show();
		});
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}
}