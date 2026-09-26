/**
 * Категории: в .catalog_preview .vmd-desc оставляем заголовок (h1|первый h2) + .vmd-subtitle,
 * остальное — за кнопкой «Подробнее». На карточке товара не трогаем.
 * SEO: в описании категории используем h2 (page H1 уже в #pagetitle).
 */
(function () {
	function isTeaser(el, index) {
		if (!el || el.nodeType !== 1) {
			return false;
		}
		var tag = el.tagName;
		if (tag === 'H1') {
			return true;
		}
		// после дедупа H1→H2: первый заголовок блока остаётся в тизере
		if (tag === 'H2' && index === 0) {
			return true;
		}
		if (tag === 'P' && el.classList.contains('vmd-subtitle')) {
			return true;
		}
		return false;
	}

	function ensureStructure(root) {
		if (root.dataset.vmdCollapse === '1') {
			return root.querySelector('.vmd-desc__toggle');
		}

		var existingMore = root.querySelector(':scope > .vmd-desc__more');
		if (existingMore) {
			var existingBtn = root.querySelector(':scope > .vmd-desc__toggle');
			if (!existingBtn) {
				existingBtn = document.createElement('button');
				existingBtn.type = 'button';
				existingBtn.className = 'vmd-desc__toggle';
				existingBtn.setAttribute('aria-expanded', 'false');
				existingMore.parentNode.insertBefore(existingBtn, existingMore);
			}
			root.classList.add('vmd-desc--collapsible', 'vmd-desc--collapsed');
			root.dataset.vmdCollapse = '1';
			return existingBtn;
		}

		var children = Array.prototype.slice.call(root.children);
		if (children.length < 3) {
			return null;
		}

		var teaserEnd = 0;
		while (teaserEnd < children.length && isTeaser(children[teaserEnd], teaserEnd)) {
			teaserEnd++;
		}
		if (teaserEnd === 0 || teaserEnd >= children.length) {
			return null;
		}

		var more = document.createElement('div');
		more.className = 'vmd-desc__more';
		children.slice(teaserEnd).forEach(function (node) {
			more.appendChild(node);
		});

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'vmd-desc__toggle';
		btn.setAttribute('aria-expanded', 'false');

		root.appendChild(btn);
		root.appendChild(more);
		root.classList.add('vmd-desc--collapsible', 'vmd-desc--collapsed');
		root.dataset.vmdCollapse = '1';
		return btn;
	}

	function setOpen(root, btn, open) {
		root.classList.toggle('vmd-desc--collapsed', !open);
		root.classList.toggle('vmd-desc--expanded', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		btn.textContent = open ? 'Свернуть' : 'Подробнее';
	}

	function wire(root) {
		var btn = ensureStructure(root);
		if (!btn) {
			return;
		}
		setOpen(root, btn, false);
		btn.addEventListener('click', function () {
			var open = root.classList.contains('vmd-desc--collapsed');
			setOpen(root, btn, open);
		});
	}

	function init() {
		var nodes = document.querySelectorAll('.catalog_preview article.vmd-desc');
		for (var i = 0; i < nodes.length; i++) {
			wire(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
