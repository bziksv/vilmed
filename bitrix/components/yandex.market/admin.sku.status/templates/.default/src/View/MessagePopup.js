const BX = window.BX;

export class MessagePopup {

	constructor(messages: Object, locale: Locale) {
		this.messages = messages;
		this.locale = locale;
	}

	show() {
		const popup = new BX.CAdminDialog({
			content: this.content(),
			width: 800,
			height: 500,
		});

		popup.Show();
	}

	content() : string {
		return Object.entries(this.messages).map(([type, group]) => `
			<div class="ym-sku-status-messages-group">${this.locale.message(type.toUpperCase())}</div>
			<ul class="ym-sku-status-messages">
				${group.map((message) => `<li class="ym-sku-status-message">
					${message['message'] ? `<div class="ym-sku-status-message__title">${message['message']}</div>` : ''}
					${message['comment'] ? `<div class="ym-sku-status-message__comment">${message['comment']}</div>` : ''}
				</li>`).join('')}
			</ul>
		`).join('');
	}

}