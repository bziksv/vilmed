import {SelectSkeleton} from "./SelectSkeleton";

export class InitialControl extends SelectSkeleton {

	_focusCallback;

	destroy() : void {
		this.unbindFocus();
		super.destroy();
	}

	bindFocus(callback: () => {}) : void {
		this._focusCallback = callback;
		this.$el.on('select2:opening', callback);
	}

	unbindFocus() : void {
		if (this._focusCallback == null) { return; }

		this.$el.off('select2:opening', this._focusCallback);
		this._focusCallback = null;
	}
}