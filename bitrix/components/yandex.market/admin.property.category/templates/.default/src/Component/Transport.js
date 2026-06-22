const BX = window.BX;

export class Transport {

	static defaults = {
		url: null,
		componentParameters: {},
	}

	_apiKeyField;

	constructor(options: Object) {
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	configure(apiKeyField: ?HTMLInputElement) : void {
		this._apiKeyField = apiKeyField;
	}

	fetch(action: string, payload: Object = {}) : Promise {
		return new Promise((resolve, reject) => {
			const formData = {
				action: action,
				payload: payload,
				componentParameters: this.options.componentParameters,
			};

			if (this._apiKeyField != null) {
				formData['apiKey'] = this._apiKeyField.value;
			}

			BX.ajax({
				url: this.options.url,
				method: 'POST',
				dataType: 'json',
				data: formData,
				onsuccess: (data) => {
					if (data.status === 'ok') {
						resolve(data.data);
					} else if (data.status === 'error') {
						reject(new Error(data.message));
					} else {
						reject(new Error('unknown response format'));
					}
				},
				onfailure: (data) => {
					reject(new Error(data.message));
				}
			});
		});
	}
}