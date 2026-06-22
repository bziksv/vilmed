import type {Transport} from "../Component/Transport";
import {ParameterCollection} from "./Dto/ParameterCollection";

export class ParametersPool {

	_pool = {};
	_fetch = {};
	transport: Transport;

	constructor(transport: Transport) {
		this.transport = transport;
	}

	set(category: string, parameterCollection: ParameterCollection) : void {
		category && (this._pool[category] = parameterCollection);
	}

	get(category: string) : Promise<ParameterCollection> {
		if (!category) {
			return Promise.resolve(new ParameterCollection([]));
		}

		if (this._pool[category] != null) {
			return Promise.resolve(this._pool[category]);
		}

		if (this._fetch[category] != null) {
			return this._fetch[category];
		}

		this._fetch[category] = this.transport.fetch('parameters', { category: category })
			.then((data) => {
				const collection = new ParameterCollection(data.parameters);

				this._pool[category] = collection;
				this._fetch[category] = null;

				return collection;
			})
			.catch((error) => {
				this._fetch[category] = null;
				throw error;
			});

		return this._fetch[category];
	}

}