export function compileTemplate(template, variables) {
	let result = template;

	if (variables == null) { return result; }

	for (const [key, value] of Object.entries(variables)) {
		const marker = '#' + key + '#';
		let previousPosition = -1;
		let position = -1;

		do {
			previousPosition = position;
			result = result.replace(marker, value);

			position = result.indexOf(marker);
		} while (position !== -1 && position > previousPosition);
	}

	return result;
}

export function htmlToElement(html, tag = 'div') {
	const renderer = document.createElement(tag);

	renderer.innerHTML = html;

	if (tag === 'table') {
		return renderer.firstElementChild.firstElementChild;
	}

	return renderer.firstElementChild;
}