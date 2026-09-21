/**
 * VILMED: не вырезать inline SVG в визуальном редакторе Bitrix.
 * Ядро fileman ставит svg:{remove:1} — из-за этого при сохранении карточки
 * пропадают иконки в .vmd-desc (.ic > svg). Разрешаем svg и дочерние теги.
 */
(function () {
	var SVG_TAGS = [
		'svg', 'path', 'circle', 'line', 'polyline', 'polygon', 'rect', 'g',
		'defs', 'use', 'symbol', 'clippath', 'ellipse', 'text', 'tspan',
		'stop', 'lineargradient', 'radialgradient', 'marker', 'pattern', 'mask'
	];

	function allowSvgRules(rules) {
		if (!rules || !rules.tags) {
			return;
		}
		for (var i = 0; i < SVG_TAGS.length; i++) {
			// Пустой объект = тег разрешён, атрибуты сохраняются.
			// Важно перезаписать svg:{remove:1} из ядра fileman.
			rules.tags[SVG_TAGS[i]] = {};
		}
	}

	/**
	 * Парсер fileman читает атрибуты через getAttribute(name.toLowerCase()).
	 * У SVG viewBox регистрозависимый: getAttribute('viewbox') === null,
	 * и в HTML уезжает viewbox="null" — иконка 24×24 рисуется без масштаба и обрезается.
	 */
	function patchSvgViewBoxRead() {
		var Parser = window.BXHtmlEditor && BXHtmlEditor.BXEditorParser;
		if (!Parser || !Parser.prototype || Parser.prototype.__vilmedViewBox || typeof Parser.prototype.GetAttributeEx !== 'function') {
			return false;
		}
		Parser.prototype.__vilmedViewBox = true;
		var orig = Parser.prototype.GetAttributeEx;
		Parser.prototype.GetAttributeEx = function (node, attributeName) {
			if (node && String(attributeName || '').toLowerCase() === 'viewbox') {
				var vb = node.getAttribute('viewBox') || node.getAttribute('viewbox') || '';
				if (!vb || vb === 'null' || !/\d/.test(vb)) {
					return '0 0 24 24';
				}
				return vb;
			}
			return orig.call(this, node, attributeName);
		};
		return true;
	}

	function patchEditor(editor) {
		patchSvgViewBoxRead();
		if (!editor || editor.__vilmedSvgOk) {
			return;
		}
		editor.__vilmedSvgOk = true;

		BX.addCustomEvent(editor, 'OnGetParseRules', function () {
			allowSvgRules(editor.rules);
		});

		if (editor.rules) {
			allowSvgRules(editor.rules);
		}

		if (typeof editor.GetParseRules === 'function') {
			var orig = editor.GetParseRules.bind(editor);
			editor.GetParseRules = function () {
				var rules = orig();
				allowSvgRules(rules);
				return rules;
			};
		}
	}

	function bind() {
		if (!window.BX || !BX.addCustomEvent) {
			setTimeout(bind, 50);
			return;
		}
		BX.addCustomEvent('OnEditorInitedBefore', patchEditor);
		BX.addCustomEvent('OnEditorInitedAfter', patchEditor);

		// На случай ленивой загрузки модуля редактора — патчим прототип.
		var tries = 0;
		(function waitProto() {
			tries++;
			patchSvgViewBoxRead();
			var Editor = (window.BXHtmlEditor && BXHtmlEditor.BXEditor)
				|| (window.BXEditor)
				|| null;
			if (Editor && Editor.prototype && !Editor.prototype.__vilmedSvgHook) {
				Editor.prototype.__vilmedSvgHook = true;
				var oldInit = Editor.prototype.Init;
				if (typeof oldInit === 'function') {
					Editor.prototype.Init = function () {
						patchEditor(this);
						return oldInit.apply(this, arguments);
					};
				}
				return;
			}
			if (tries < 80) {
				setTimeout(waitProto, 100);
			}
		})();
	}

	bind();
})();
