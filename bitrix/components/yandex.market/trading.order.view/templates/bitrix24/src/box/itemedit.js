import ItemView from "./itemview";

export default class ItemEdit extends ItemView {

	destroy() : void {
		this.unbind();
		super.destroy();
	}

	unbind() : void {
		if (this.el == null) { return; }

		this.handleDeleteClick(false);
	}

	handleDeleteClick(dir: boolean) : void {
		const button = this.el.querySelector('[data-action="DELETE"]');

		button[dir ? 'addEventListener' : 'removeEventListener']('click', this.onDeleteClick);
	}

	onDeleteClick = (evt) => {
		this.el.dispatchEvent(new CustomEvent('yamarketBoxDelete', {
			detail: { item: this },
			bubbles: true,
		}));

		evt.preventDefault();
	}

	render(box: Object, basket: Object) : void {
		super.render(box, basket);
		this.handleDeleteClick(true);
	}

	columnCount(basket: Object) : number {
		return super.columnCount(basket) + 1;
	}

	enableDelete(enable: boolean) : void {
		const button = this.el.querySelector('[data-action="DELETE"]');

		button.disabled = !enable;
		button.hidden = !enable;
	}

	buildActions(value) {
		return `<button
			class="yamarket-icon-action"
			type="button"
			title="${this.getMessage('BOX_DELETE')}"
			data-action="DELETE"
		>
			<svg class="yamarket-icon-action__draw" viewBox="0 0 14 15">
				<path d="M13.126 1.406v.933a.47.47 0 0 1-.47.469H.47A.47.47 0 0 1 0 2.338v-.932c0-.259.21-.47.47-.47h3.512L4.258.39A.76.76 0 0 1 4.884 0h3.353c.262.014.5.16.63.389l.277.548h3.516a.47.47 0 0 1 .466.47zM.939 3.75h11.25l-.626 9.932A1.424 1.424 0 0 1 10.16 15H2.963a1.424 1.424 0 0 1-1.4-1.318L.94 3.75z" fill="currentColor" fill-rule="evenodd"/>
			</svg>
		</button>`;
	}

	basketOptions(): Object {
		// noinspection JSUnresolvedReference
		return Object.assign(super.basketOptions(), {
			mode: BX.UI.EntityEditorMode.edit,
		});
	}
}