/**
 * После сохранения в визуальном редакторе Bitrix у Lucide-иконок
 * viewBox превращается в viewbox="null", и штрих обрезается.
 * Восстанавливаем сетку 24×24, пока текст в БД ещё не пересохранён.
 */
(function () {
	function fix(root) {
		var list = (root || document).querySelectorAll('.vmd-desc svg');
		for (var i = 0; i < list.length; i++) {
			var vb = list[i].getAttribute('viewBox') || list[i].getAttribute('viewbox') || '';
			if (!vb || vb === 'null' || !/\d/.test(vb)) {
				list[i].setAttribute('viewBox', '0 0 24 24');
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { fix(document); });
	} else {
		fix(document);
	}
})();
