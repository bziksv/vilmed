(function(BX, $) {

	const Plugin = BX.namespace('YandexMarket.Plugin');
	const Admin = BX.namespace('YandexMarket.Ui.Admin');
	const utils = BX.namespace('YandexMarket.Utils');

	const constructor = Admin.ExportForm = Plugin.Base.extend({

		defaults: {
			messageElement: '.js-export-form__message',

			runButtonElement: '.js-export-form__run-button',
			stopButtonElement: '.js-export-form__stop-button',

			timerHolderElement: '.js-export-form__timer-holder',
			timerElement: '.js-export-form__timer',

			errorTemplate: '<div class="adm-info-message-wrap adm-info-message-red"><div class="adm-info-message"><div class="adm-info-message-title">#TITLE#</div><textarea cols="60" rows="5"></textarea><div class="adm-info-message-icon"></div></div></div>',

			langPrefix: 'YANDEX_MARKET_EXPORT_FORM_',
			lang: {}
		},

		initVars: function() {
			this.callParent('initVars', constructor);

			this._formData = null;
			this._query = null;
			this._queryTimeout = null;
			this._state = null;
			this._timerInterval = null;
		},

		initialize: function() {
			this.bind();
		},

		destroy: function() {
			this.unbind();
			this.callParent('destroy', constructor);
		},

		bind: function() {
			this.handleRunClick(true);
			this.handleStopClick(true);
		},

		unbind: function() {
			this.handleRunClick(false);
			this.handleStopClick(false);
		},

		handleRunClick: function(dir) {
			const button = this.getElement('runButton');

			button[dir ? 'on' : 'off']('click', $.proxy(this.onRunClick, this));
		},

		handleStopClick: function(dir) {
			const button = this.getElement('stopButton');

			button[dir ? 'on' : 'off']('click', $.proxy(this.onStopClick, this));
		},

		onRunClick: function() {
			this.run();
		},

		onStopClick: function() {
			this.query('stop');
		},

		queryDelayed: function(action, delay) {
			this.queryDelayedCancel();

			this._queryTimeout = setTimeout(
				$.proxy(this.query, this, action),
				(parseInt(delay, 10) || 1) * 1000
			);
		},

		queryDelayedCancel: function() {
			clearTimeout(this._queryTimeout);
		},

		run: function() {
			this.switchButtons('run');
			this.showMessage('');
			this.startTimer();
			this.query('run');
		},

		query: function(action) {
			this.queryDelayedCancel();
			this.queryCancel(true);

			this._query = this.makeQuery(action);

			this._query.then(
				$.proxy(this.queryEnd, this),
				$.proxy(this.queryStop, this)
			);
		},

		queryCancel: function(isSilent) {
			if (this._query !== null) {
				this._query.abort(isSilent ? 'silent' : 'manual');
			}
		},

		queryStop: function(xhr, textStatus) {
			let message;

			this._query = null;

			if (textStatus === 'silent') { return; }

			message = this.buildQueryErrorMessage(xhr, textStatus);

			this.showMessage(message);
			this.resetButtons();
			this.releaseFormData();
			this.releaseState();
			this.stopTimer();
		},

		queryEnd: function(response, textStatus, xhr) {
			let data;

			try {
				data = (typeof response === 'string') ? $.parseJSON(response) : response;

				if (!$.isPlainObject(data)) {
					this.queryStop(xhr, 'parseError');
					return;
				}
			} catch (e) {
				this.queryStop(xhr, 'parseError');
				return;
			}

			this._query = null;

			this.showMessage(data.message);

			switch (data.status) {
				case 'progress':
					this.queryDelayed('run', data.state['TIMEOUT'] || this.getFormValue('TIME_SLEEP'));
					this.setState(data.state);
				break;

				default:
					this.resetButtons();
					this.releaseFormData();
					this.releaseState();
					this.stopTimer();
				break;
			}
		},

		makeQuery: function(action) {
			const config = {
				url: this.$el.attr('action'),
				type: 'post',
				data: this.getFormData()
			};
			const state = this.getState();

			config.data.push({
				name: 'action',
				value: action
			});

			if (state !== null) {
				for (let stateKey in state) {
					if (state.hasOwnProperty(stateKey)) {
						config.data.push({
							name: stateKey,
							value: state[stateKey]
						});
					}
				}
			}

			return $.ajax(config);
		},

		showMessage: function(text) {
			const messageElement = this.getElement('message');

			if (text instanceof $) {
				messageElement.empty().append(text);
			} else {
				messageElement.html(text || '');
			}
		},

		switchButtons: function(action) {
			const runButton = this.getElement('runButton');
			const stopButton = this.getElement('stopButton');

			runButton.prop('disabled', (action === 'run'));
			stopButton.prop('disabled', (action === 'stop'));
		},

		resetButtons: function() {
			this.switchButtons('stop');
		},

		getState: function() {
			return this._state;
		},

		setState: function(state) {
			this._state = state;
		},

		releaseState: function() {
			this._state = null;
		},

		getFormValue: function(field) {
			const formData = this.getFormData();
			let result;

			for (let i = formData.length - 1; i >= 0; i--) {
				if (formData[i].name === field) {
					result = formData[i].value;
					break;
				}
			}

			return result;
		},

		getFormData: function() {
			if (this._formData === null) {
				this._formData = this.$el.serializeArray();
			}

			return this._formData.slice();
		},

		releaseFormData: function() {
			this._formData = null;
		},

		startTimer: function() {
			const startDate = new Date();
			const timerElement = this.getElement('timer');
			const timerHolderElement = this.getElement('timerHolder');

			timerHolderElement.removeClass('is--hidden');
			timerElement.text('00:00');

			clearTimeout(this._timerInterval);

			this._timerInterval = setInterval(function() {
				const nowDate = new Date();
				const diff = (nowDate - startDate) / 1000;
				let seconds = '' + parseInt(diff % 60, 10);
				let minutes = '' + Math.floor(diff / 60);

				if (minutes.length === 1) {
					minutes = '0' + minutes;
				}

				if (seconds.length === 1) {
					seconds = '0' + seconds;
				}

				timerElement.text(minutes + ':' + seconds);
			}, 1000);
		},

		stopTimer: function() {
			clearTimeout(this._timerInterval);
		},

		buildQueryErrorMessage: function(xhr, textStatus) {
			const template = this.getTemplate('error');
			const html = utils.compileTemplate(template, {
				'TITLE': this.getLang('QUERY_ERROR_TITLE')
			});
			const result = $(html);
			const text = this.getLang('QUERY_ERROR_TEXT', {
				'HTTP_STATUS': xhr && xhr.status,
				'TEXT_STATUS': textStatus,
				'RESPONSE': xhr && xhr.responseText
			});

			result.find('textarea').val(text);

			return result;
		}

	}, {
		dataName: 'uiAdminExportForm'
	});

})(BX, jQuery);