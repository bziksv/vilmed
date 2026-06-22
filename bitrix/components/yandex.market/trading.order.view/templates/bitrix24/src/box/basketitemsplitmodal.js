import {collectValues} from "../utils";

export default class BasketItemSplitModal {

	static defaults = {
		messages: {},
	}

	constructor(options: Object = {}) {
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	onSaveClick(messageBox, resolve, reject) : void {
		const values = collectValues(messageBox.popupWindow.contentContainer);
		const count = parseInt(values['COUNT']) || 0;

		messageBox.close();
		count >= 2 ? resolve(count) : reject();
	}

	onCancelClick(messageBox, reject) : void {
		messageBox.close();
		reject();
	}

	open() : Promise {
		return new Promise((resolve, reject) => {
			const options  = this.dialogOptions();
			const messageBox = BX.UI.Dialogs.MessageBox.create(options);

			messageBox.setMessage(this.buildForm());
			messageBox.setButtons([
				new BX.UI.SaveButton({
					events: {
						click: this.onSaveClick.bind(this, messageBox, resolve, reject),
					}
				}),
				new BX.UI.CancelButton({
					events: {
						click: this.onCancelClick.bind(this, messageBox, reject),
					}
				}),
			]);

			messageBox.show();
		});
	}

	dialogOptions() : Object {
		return {
			title: this.getMessage('ITEM_SPLIT_TITLE'),
		};
	}

	buildForm() : string {
		return `<div class="ui-form-row">
			<div class="ui-form-label">
				<div class="ui-ctl-label-text">${this.getMessage('ITEM_SPLIT_COUNT')}</div>
			</div>
			<div class="ui-form-content">
				<div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
					<input class="ui-ctl-element" type="number" name="COUNT" value="2" min="2" max="99" />
				</div>
			</div>
		</div>`;
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}
}