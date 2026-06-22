import type {SkuStatusFactory} from "../SkuStatusFactory";
import type {Locale} from "./Locale";

export class Repository {

	static defaults = {
		url: null,
		limit: null,
		delay: 1000,
		payload: {},
	};

	constructor(factory: SkuStatusFactory, locale: Locale, options: Object) {
		this.factory = factory;
		this.locale = locale;
		this.options = Object.assign({}, Repository.defaults, options);
		this._loadQueue = [];
		this._loadPromises = {};
		this._loadResolvers = {};
		this._loadTimeout = null;
		this._loaded = {};
		this._businesses = null;
	}

	load(id: number) : Promise {
		if (this._loaded[id] instanceof Error) {
			return Promise.reject(this._loaded[id]);
		} else if (this._loaded[id] != null) {
			return Promise.resolve({
				element: this._loaded[id],
				businesses: this._businesses,
			});
		}

		if (this._loadPromises[id] != null) {
			return this._loadPromises[id];
		}

		this._loadQueue.push(id);
		this._loadPromises[id] = new Promise((resolve, reject) => {
			this._loadResolvers[id] = {
				resolve: resolve,
				reject: reject,
			};

			if (this._loadTimeout == null) {
				this._loadTimeout = setTimeout(this.loadStart, this.options.delay);
			}
		});

		return this._loadPromises[id];
	}

	loadStart = () : void => {
		this._loadTimeout = null;

		if (this._loadQueue.length === 0) { return; }

		const ids = this.fulfilQueue(this._loadQueue).slice();

		for (const idsChunk of this.chunkQueue(ids)) {
			BX.ajax({
				url: this.options.url,
				method: 'POST',
				dataType: 'json',
				data: Object.assign({}, this.options.payload, { id: idsChunk }),
				onsuccess: (response: Object) : void => {
					if (response == null || typeof response !== 'object' || response.status == null) {
						this.rejectAll(ids, new Error(this.locale.message('UNKNOWN_RESPONSE_FORMAT')));
					} else if (response.status === 'ok') {
						this.resolve(ids, response.data.elements, response.data.businesses);
					} else if (response.status === 'error') {
						this.rejectAll(ids, new Error(response.message));
					} else {
						this.rejectAll(ids, new Error(this.locale.message('UNKNOWN_RESPONSE_FORMAT')));
					}
				},
				onfailure: (message, status) => {
					const error = status instanceof Error ? status : new Error(`${message} ${status}`);

					this.rejectAll(ids, error);
				}
			});
		}

		this._loadQueue = [];
	}

	resolve(ids: number[], elements: Object, businesses: Object) : void {
		this._businesses = businesses;

		for (const id of ids) {
			if (this._loadResolvers[id] == null) { continue; }

			if (elements[id] != null) {
				this._loaded[id] = elements[id];
				this._loadResolvers[id].resolve({
					element: elements[id],
					businesses: businesses,
				});
			} else {
				const error = new Error(this.locale.message('MISSING_DATA'))

				this._loaded[id] = error;
				this._loadResolvers[id].reject(error);
			}

			delete this._loadPromises[id];
			delete this._loadResolvers[id];
		}
	}

	rejectAll(ids: number, error: Error) {
		for (const id of ids) {
			if (this._loadResolvers[id] == null) { continue; }

			this._loaded = error;
			this._loadResolvers[id].reject(error);

			delete this._loadPromises[id];
			delete this._loadResolvers[id];
		}
	}

	fulfilQueue(ids: number[]) : number[] {
		if (this.options.limit == null) { return ids; }

		const limit = +this.options.limit;
		const left = (limit - (ids.length % limit));

		for (const instance of this.factory.fulfilQueue(left, ids, this._loaded)) {
			const id = instance.id();

			this._loadPromises[id] = new Promise((resolve, reject) => {
				this._loadResolvers[id] = {
					resolve: resolve,
					reject: reject,
				};
			});

			instance.waitLoad(this._loadPromises[id]);
			ids.push(id);
		}

		return ids;
	}

	chunkQueue(ids: number[]) : number[][] {
		if (this.options.limit == null) { return [ ids ]; }

		const limit = +this.options.limit;

		const chunks = [];

		for (let i = 0; i < ids.length; i += limit) {
			chunks.push(ids.slice(i, i + limit));
		}

		return chunks;
	}

}