import ItemView from "./itemview";
import BoxField from "../box/field";
import {kebabCase} from "../utils";

export default class ItemEdit extends ItemView {

	static defaults = Object.assign({}, ItemView.defaults);

	_moreDropdown;

	destroy() {
		this.destroyMoreDropdown();
		super.destroy();
	}

	bind() : void {
		this.handleInputChange(true);
		this.handleMoveClick(true);
		this.handleMoreClick(true);
	}

	unbind() : void {
		this.handleInputChange(false);
		this.handleMoveClick(false);
		this.handleMoreClick(false);
	}

	handleInputChange(dir: boolean) : void {
		if (!this.options.onChange) { return; }

		this.el.querySelectorAll('input').forEach((input: HTMLInputElement) => {
			if (input.name === this.getName('COUNT')) {
				input[dir ? 'addEventListener' : 'removeEventListener']('change', this.onCountChange);
			}

			input[dir ? 'addEventListener' : 'removeEventListener']('change', this.options.onChange);
		});
	}

	handleMoveClick(dir: boolean) : void {
		if (!this.hasAction(BoxField.ACTION_BOX)) { return; }

		this.el.querySelector('[data-action="up"]')
			?.[dir ? 'addEventListener' : 'removeEventListener']('click', this.onMoveClick);

		this.el.querySelector('[data-action="down"]')
			?.[dir ? 'addEventListener' : 'removeEventListener']('click', this.onMoveClick);
	}

	handleMoreClick(dir: boolean) : void {
		if (!this.hasAction(BoxField.ACTION_ITEM)) { return; }

		this.el
			.querySelector('[data-action="more"]')
			?.[dir ? 'addEventListener' : 'removeEventListener']('click', this.onMoreClick);
	}

	onCountChange = (evt) : void => {
		const count = parseInt(evt.target.value);

		if (isNaN(count)) { return; }

		this.callWires('updateTotal', [count]);
	}

	onMoveClick = (evt) : void => {
		this.fire('move', {
			direction: evt.currentTarget.dataset.action,
		});
	}

	onMoreClick = () : void => {
		const moreDropdown = this.moreDropdown();

		this.renewMoreActions(moreDropdown);
		moreDropdown.popupWindow.show();
	}

	disableInput(dir: boolean) : void {
		this.el.querySelectorAll('input').forEach((input) => {
			if (input.name === this.getName('DELETE')) { return; }

			input.readonly = dir;

			if (input.classList.contains('ui-ctl-element')) {
				input.parentElement.classList.toggle('ui-ctl-disabled', dir);
			}
		});
	}

	replaceName(newName: string) {
		this.destroyMoreDropdown();
		super.replaceName(newName);
	}

	getCountDiff() : number {
		const needDelete = this.getInputValue('DELETE');
		const initialCount = parseInt(this.getInputValue('INITIAL_COUNT'));
		const count = parseInt(this.getInputValue('COUNT'));
		let result;

		if (needDelete) {
			result = initialCount;
		} else {
			result = initialCount - count;
		}

		return result;
	}

	enableUp(enable: boolean = true) : void {
		this.el.querySelector('[data-action="up"]').disabled = !enable;
	}

	enableDown(enable: boolean = true) : void {
		this.el.querySelector('[data-action="down"]').disabled = !enable;
	}
	
	columnIndex(item: Object, basketItem: Object, column: string): string {
		if (!this.hasAction(BoxField.ACTION_BOX)) { return super.columnIndex(item, basketItem, column); }

		return `<td class="for--${kebabCase(column)}"><!--
			-->${this.indexHidden(item, basketItem)}<!--
			--><button class="yamarket-icon-action" type="button" data-action="down">
				<svg class="yamarket-icon-action__draw" viewBox="0 0 11.314 16.657">
					<path d="M4.657 0h2v12.828l3.242-3.242L11.314 11l-5.657 5.657L0 11l1.414-1.414 3.243 3.242V0Z" fill="currentColor"/>
				</svg>
			</button><!--
			--><button class="yamarket-icon-action" type="button" data-action="up">
				<svg class="yamarket-icon-action__draw" viewBox="0 0 11.314 17.416">
					<path d="M11.314 5.67 9.896 7.081l-3.255-3.27-.014 13.605-2-.002.014-13.568-3.23 3.215L0 5.644 5.67 0l5.644 5.67Z" fill="currentColor"/>
				</svg>
			</button><!--
		--></td>`;
	}

	columnCount(item: Object, basketItem: Object, column: string) : string {
		if (!this.hasAction(BoxField.ACTION_ITEM)) { return super.columnCount(item, basketItem, column); }

		const value = this.value(item, basketItem, column);
		const valueSanitized = parseFloat(value) || '';
		const partialCurrent = item?.PARTIAL_COUNT?.CURRENT;
		const partialTotal = item?.PARTIAL_COUNT?.TOTAL;

		return `<td class="for--${kebabCase(column)}">
			<input type="hidden" name="${this.getName('INITIAL_COUNT')}" value="${valueSanitized}" />
			<input type="hidden" name="${this.getName('OFFSET')}" value="${parseInt(this.value(item, basketItem, 'OFFSET')) || 0}" />
			<input type="hidden" name="${this.getName('PARTIAL_CURRENT')}" value="${partialCurrent || ''}" />
			<input type="hidden" name="${this.getName('PARTIAL_TOTAL')}" value="${partialTotal || ''}" />
			<div class="ui-ctl ui-ctl-sm ui-ctl-textbox ui-ctl-w100" data-entity="COUNT">
				<input
					class="ui-ctl-element"
					type="number"
					name="${this.getName('COUNT')}"
					value="${valueSanitized}"
					min="1"
					max="${valueSanitized}"
					step="1"
				/>
			</div>
			<span data-entity="PARTIAL_TEXT">${this.partialText(partialCurrent, partialTotal)}</span>
		</td>`;
	}

	setCount(count: number) : void {
		this.getInput('COUNT').value = count;
	}

	setInitialCount(count: number) {
		this.getInput('COUNT').max = count;
		super.setInitialCount(count);
	}

	renderActions() : string {
		if (!this.hasAction(BoxField.ACTION_ITEM)) { return ''; }

		return `<td class="for--delete">
			<input type="hidden" name="${this.getName('DELETE')}" value="" />
			<button class="yamarket-icon-action" type="button" data-action="more">
				<svg class="yamarket-icon-action__draw" viewBox="0 0 14 3.5">
					<path d="M3.5 1.75C3.5 2.71653 2.7165 3.5 1.75 3.5C0.783501 3.5 0 2.71653 0 1.75C0 0.783475 0.783501 0 1.75 0C2.7165 0 3.5 0.783475 3.5 1.75L3.5 1.75Z" fill="currentColor" />
					<path d="M8.75 1.75C8.75 2.71653 7.96653 3.5 7 3.5C6.03347 3.5 5.25 2.71653 5.25 1.75C5.25 0.783475 6.03347 0 7 0C7.96653 0 8.75 0.783475 8.75 1.75L8.75 1.75Z" fill="currentColor" />
					<path d="M12.25 3.5C13.2165 3.5 14 2.71653 14 1.75C14 0.783475 13.2165 0 12.25 0C11.2835 0 10.5 0.783475 10.5 1.75C10.5 2.71653 11.2835 3.5 12.25 3.5L12.25 3.5Z" fill="currentColor" />
				</svg>
			</button>
		</td>`;
	}

	destroyMoreDropdown() : void {
		if (this._moreDropdown == null) { return; }

		BX.PopupMenu.destroy(`${this.options.name}-more`);
		this._moreDropdown = null;
	}

	moreDropdown() : BX.PopupMenu {
		if (this._moreDropdown == null) {
			this._moreDropdown = BX.PopupMenu.create(
				`${this.options.name}-more`,
				this.el.querySelector('[data-action="more"]'),
				[]
			);
		}

		return this._moreDropdown;
	}

	renewMoreActions(dropdown: BX.PopupMenu) : void {
		const previousIds = dropdown.getMenuItems().map((menuItem) => menuItem.id);

		for (const previousId of previousIds) {
			dropdown.removeMenuItem(previousId, { destroyEmptyPopup: false });
		}

		for (const action of this.moreActions(dropdown)) {
			dropdown.addMenuItem(action);
		}
	}

	moreActions(dropdown: BX.PopupMenu) : Array {
		return (
			this.moreActionsForSplit(dropdown)
				.concat(this.moreActionsForDelete(dropdown))
		);
	}

	moreActionsForDelete(dropdown: BX.PopupMenu) : Array {
		if (!this.hasAction(BoxField.ACTION_ITEM) || this.isPart()) { return []; }

		const result = [];
		const deleteInput = this.getInput('DELETE');

		if (deleteInput.value === 'Y') {
			result.push({
				onclick: () => {
					dropdown.popupWindow.close();
					this.markDeleted(false);
				},
				text: this.getMessage('ITEM_CANCEL_DELETE'),
			});
		} else {
			result.push({
				onclick: () => {
					dropdown.popupWindow.close();
					this.markDeleted(true);
				},
				text: this.getMessage('ITEM_DELETE'),
			});
		}

		return result;
	}

	moreActionsForSplit(dropdown: BX.PopupMenu) : Array {
		if (!this.hasAction(BoxField.ACTION_BOX) || this.getInputValue('DELETE') === 'Y') { return []; }

		const result = [];
		const currentInput = this.getInput('PARTIAL_CURRENT');

		if (currentInput.value > 0) {
			result.push({
				onclick: () => {
					dropdown.popupWindow.close();
					this.fire('cancelSplit');
				},
				text: this.getMessage('ITEM_CANCEL_SPLIT'),
			});
		} else {
			result.push({
				onclick: () => {
					dropdown.popupWindow.close();
					this.fire('split');
				},
				text: this.getMessage('ITEM_SPLIT'),
			});
		}

		return result;
	}

	markDeleted(needDelete: boolean = true) : void {
		const deleteInput = this.getInput('DELETE');

		deleteInput.value = needDelete ? 'Y' : '';
		this.el.classList[needDelete ? 'add' : 'remove']('is--deleted');
		this.disableInput(needDelete);

		this.options.onChange();
	}
}