class ReportDownloader {
	STATUS_PENDING = 'PENDING';
	STATUS_PROCESSING = 'PROCESSING';
	STATUS_FAILED = 'FAILED';
	STATUS_DONE = 'DONE';

	defaults = {
		url: null,
		setupId: null,
		reportId: null,
		sessid: null,
		waitTime: 0,
		lang: {},
	};

	constructor(selector, options) {
		this.el = document.querySelector(selector);
		this.options = Object.assign({}, this.defaults, options);
	}

	start() {
		this.wait(this.options.waitTime)
			.then(() => { this.fetch() });
	}

	step(time) {
		this.wait(time)
			.then(() => { this.fetch() });
	}

	wait(time) {
		return new Promise((resolve) => {
			let left = time;
			const step = 1000;
			const tick = () => {
				left -= step;

				if (left <= 0) {
					resolve();
					return;
				}

				this.showWait(left);
				setTimeout(tick, step);
			};

			tick();
		});
	}

	showWait(time) {
		const seconds = Math.round(time / 1000);
		const unit = this.plural(seconds, 'SECOND');

		this.el.textContent = `${this.locale('WAIT')} ${seconds} ${unit}`;
	}

	fetch() {
		this.showFetch();

		fetch(this.options.url, {
			method: 'POST',
			body: this.fetchBody()
		})
			.then((raw) => raw.json())
			.then((response) => {
				if (response['status'] === this.STATUS_DONE) {
					this.openFile(response['file']);
					return;
				}

				if (response['status'] === this.STATUS_FAILED) {
					throw new Error(response['error']);
				}

				if (
					response['status'] !== this.STATUS_PENDING
					&& response['status'] !== this.STATUS_PROCESSING
				) {
					throw new Error(this.locale('UNKNOWN_STATUS'));
				}

				this.options.sessid = response.sessid;
				this.step(response['wait']);
			})
			.catch((error) => {
				this.showError(error);
			});
	}

	fetchBody() {
		const body = new FormData();

		body.append('sessid', this.options.sessid);
		body.append('setup', this.options.setupId);
		body.append('report', this.options.reportId);

		return body;
	}

	showFetch() {
		this.el.textContent = `${this.locale('FETCH')}`;
	}

	openFile(url) {
		this.el.innerHTML = `<a href="${url}">${this.locale('FILE')}</a>`;
		window.location.href = url;
	}

	showError(error) {
		this.el.textContent = error.message;
	}

	locale(key) {
		return this.options.lang[key] || key;
	}

	plural(number, baseKey) {
		if (number % 100 > 4 && number % 100 < 20) {
			return this.locale(`${baseKey}_5`);
		}

		const cases = [5, 1, 2, 2, 2, 5];
		const form = cases[Math.min(number % 10, 5)];

		return this.locale(`${baseKey}_${form}`);
	}

}