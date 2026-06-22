export class Locale {

    constructor(messages: Object) {
        this._messages = messages;
    }

    message(key: string) : string {
        return this._messages[key] || key;
    }
}