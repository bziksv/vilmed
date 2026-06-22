import {compileTemplate} from "./utils";
import type {Locale} from "./Locale";

export class State {

	static defaults = {
		loadingTemplate: '#LOADING#...',
		waitingTemplate: '',
		errorTemplate: '#MESSAGE#',
	};

	locale: Locale;

	constructor(element: HTMLElement, locale: Locale, options: Object = {}) {
		this.el = element;
		this.locale = locale;
		this.options = Object.assign({}, this.constructor.defaults, options);
	}

	loading() : void {
		this.state('loading', {
			LOADING: this.locale.message('LOADING'),
		});
	}

	waiting() : void {
		this.state('waiting');
	}

	error(reason: Error) : void {
		this.state('error', {
			MESSAGE: reason.message,
		});
	}

	state(type: string, vars: Object = {}) : void {
		const html = this.compileTemplate(type, vars);

		this.markTypeClass(type);
		this.replaceHtml(html);
	}

	compileTemplate(type: string, vars: Object = {}) : string {
		const template = this.options[type + 'Template'];

		return compileTemplate(template, vars);
	}

	markTypeClass(type: string) : void {
		const states = [
			'loading',
			'waiting',
			'error',
		];

		for (const state of states) {
			if (state === type) {
				this.el.classList.add(`is--${state}`);
			} else {
				this.el.classList.remove(`is--${state}`);
			}
		}
	}

	replaceHtml(html: string) : void {
		this.el.innerHTML = html;
	}
}