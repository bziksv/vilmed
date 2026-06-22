import type {CategoryField} from "../CategoryField";
import {ParameterCollection} from "./Dto/ParameterCollection";
import type {Parameter} from "./Dto/Parameter";
import type {State} from "../Component/State";
import type {ParametersPool} from "./ParametersPool";

export class ParametersRegistry {

	_parameters: ParameterCollection;
	_parametersPromise: Promise<ParameterCollection>;
	_parametersResolve;
	_itemWaiting = [];

	constructor(category: CategoryField, parametersPool: ParametersPool, state: State, locale: Locale) {
		this.category = category;
		this.parametersPool = parametersPool;
		this.state = state;
		this.locale = locale;
	}

	initialLoad() : Promise {
		if (this._parameters != null) { return Promise.resolve(this._parameters); }

		return this.fetch();
	}

	loaded() : boolean {
		return this._parameters != null;
	}

	reset(parameterCollection: ParameterCollection) : void {
		this._parameters = parameterCollection;
		this.resolveWaiting();
		this.resolveFetch();
	}

	collection() : Promise<ParameterCollection> {
		if (this._parameters != null) { return Promise.resolve(this._parameters); }

		return this.fetch();
	}

	stopWait(id: number, callback: () => {}) : void {
		for (const index of this._itemWaiting.keys()) {
			const [waitingId, waitingCallback] = this._itemWaiting[index];

			if (waitingId === id && waitingCallback === callback) {
				this._itemWaiting.splice(index, 1);
				break;
			}
		}
	}

	wait(id: number, callback: () => {}) : void {
		if (this._parameters != null) {
			this.resolveWaitingItem(id, callback);
			return;
		}

		this._itemWaiting.push([ id, callback ]);
	}

	resolveWaiting() : void {
		for (const [id, callback] of this._itemWaiting) {
			this.resolveWaitingItem(id, callback);
		}

		this._itemWaiting = [];
	}

	resolveWaitingItem(id: number, callback: () => {}) : void {
		const parameter = this._parameters.item(id);

		if (parameter == null) { return; }

		callback(parameter);
	}

	item(id: number) : Promise<Parameter> {
		return this.collection()
			.then((parameterCollection: ParameterCollection) => {
				const item = parameterCollection.item(id);

				if (item == null) {
					throw new Error(`not found parameter {id}`);
				}

				return item;
			});
	}

	fetch() : Promise<ParameterCollection> {
		if (this._parametersPromise != null) { return this._parametersPromise; }

		this._parametersPromise = new Promise((resolve, reject) => {
			const category = this.category.value() || this.category.parentValue();

			if (!category) {
				reject(new Error(this.locale.message('CATEGORY_EMPTY')));
				return;
			}

			this._parametersResolve = resolve;
			this.state.loading();

			this.parametersPool.get(category)
				.then((parameterCollection) => {
					this._parametersResolve = null;
					this._parametersPromise = null;
					this.state.waiting();
					this.reset(parameterCollection);
					resolve(parameterCollection);
				})
				.catch((error: Error) => {
					this._parametersResolve = null;
					this._parametersPromise = null;
					this.state.error(error);
					reject(error);
				});
		});

		return this._parametersPromise;
	}

	resolveFetch() : void {
		if (this._parametersResolve == null) { return; }

		this._parametersResolve(this._parameters);
		this._parametersResolve = null;
		this._parametersPromise = null;
	}
}