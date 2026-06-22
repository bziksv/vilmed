import ItemView from "./itemview";
import {collectValues} from "../utils";

export default class BasketItemMoveModal {

	static defaults = {
		count: 0,
		current: null,
		selected: null,
		items: [],
		messages: {},
	}

	constructor(options: Object = {}) {
		this.options = Object.assign({}, BasketItemMoveModal.defaults, options);
	}

	onSaveClick(messageBox, resolve, reject) : void {
		const values = collectValues(messageBox.popupWindow.contentContainer);
		const count = parseInt(values['COUNT']) || 0;

		if (count <= 0) {
			reject();
			return;
		}

		messageBox.close();
		resolve([
			values['BOX'] === 'new' ? null : this.options.items[values['BOX']],
			count
		]);
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
			title: this.getMessage('ITEM_MOVE_TITLE'),
		};
	}

	buildForm() : string {
		let foundBox = false;

		return `<div class="ui-form-row">
			<div class="ui-form-label">
				<div class="ui-ctl-label-text">${this.getMessage('ITEM_MOVE_COUNT')}</div>
			</div>
			<div class="ui-form-content">
				<div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
					<input class="ui-ctl-element" type="number" name="COUNT" value="${parseInt(this.options.count) || 0}" min="1" max="${parseInt(this.options.count) || 0}" />
				</div>
			</div>
		</div>
		<div class="ui-form-row">
			<div class="ui-form-label">
				<div class="ui-ctl-label-text">${this.getMessage('ITEM_MOVE_BOX')}</div>
			</div>
			<div class="ui-form-content">
				<div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100">
					<div class="ui-ctl-after ui-ctl-icon-angle"></div>
					<select class="ui-ctl-element" name="BOX">
						${this.options.items.map((item: ItemView, index: number) : string => {
							if (item === this.options.current) { return ''; }
							
							const selected = (item === this.options.selected);
							
							if (selected) { foundBox = true; }
							
							return `<option value="${index}" ${selected ? 'selected' : ''}>${item.title()}</option>`;
						})}
						<option value="new" ${!foundBox ? 'selected' : ''}>${this.getMessage('ITEM_MOVE_BOX_NEW')}</option>
					</select>
				</div>
			</div>
		</div>`;
	}

	getMessage(key: string) : string {
		return this.options.messages[key] || key;
	}
}