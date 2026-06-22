import {compileTemplate} from "./utils";

export class Locale {

    constructor(messages: Object, language: string) {
        this._messages = messages;
		this._language = language;
    }

    message(key: string, replaces: Array = null) : string {
        const message = this._messages[key] || '';

        return compileTemplate(message, replaces);
    }

	language() : string {
		return this._language;
	}
}