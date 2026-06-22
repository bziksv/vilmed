export class Control {

	constructor(element: HTMLInputElement|HTMLSelectElement) {
		this.el = element;
	}

	destroy() : void {
		this.el = null;
	}

	boot() : void {}

	value(): string|string[] {
		return this.el.value;
	}

	setValue(value: string|string[]) : void {
		this.el.value = value != null ? value : '';
	}

	focused(): boolean {
		return (document.activeElement === this.el);
	}

	focus() {
		this.el.focus();
	}
}