<?php

namespace Titlo\Relevance;

/**
 * Общие ассеты админки модуля (единый CSS).
 */
class AdminUi
{
	/** @var bool */
	protected static $cssDone = false;

	/** @var bool */
	protected static $sectionBranchJsDone = false;

	public static function renderCss(): void
	{
		if (self::$cssDone) {
			return;
		}
		self::$cssDone = true;

		$rel = '/local/modules/titlo.relevance/admin/css/titlo-relevance.css';
		$abs = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $rel;
		$v = is_file($abs) ? (string) filemtime($abs) : '1';
		echo '<link rel="stylesheet" href="' . htmlspecialcharsbx($rel) . '?v=' . htmlspecialcharsbx($v) . '">' . "\n";
	}

	/**
	 * Фильтр «ветка каталога»: поиск + множественный выбор разделов.
	 *
	 * @param int[] $selectedIds выбранные разделы (пусто = все)
	 * @param string $entityHint подсказка: «товары» | «категории»
	 */
	public static function renderSectionBranchFilter(array $selectedIds = [], string $entityHint = 'товары'): void
	{
		$selectedIds = CatalogRepository::normalizeSectionIds($selectedIds);
		$selectedMap = array_fill_keys($selectedIds, true);
		$tree = CatalogRepository::listSectionTreeForSelect();
		$hint = $entityHint === 'категории'
			? 'Выберите один или несколько разделов: покажем их и все вложенные категории.'
			: 'Выберите один или несколько разделов: покажем товары из них и всех вложенных подразделов.';
		$count = count($selectedIds);
		$btnLabel = $count === 0
			? 'Все разделы'
			: ('Выбрано: ' . $count);
		?>
		<div class="titlo-section-branch" id="titlo-section-branch-wrap">
			<div class="titlo-section-branch__head">
				<span class="titlo-section-branch__title">Ветка каталога</span>
				<span class="titlo-help" tabindex="0" aria-label="Ветка каталога">?
					<span class="titlo-help__tip">
						<?= htmlspecialcharsbx($hint) ?><br>
						Поиск сужает список. Можно отметить несколько веток — в списке будут элементы из любой из них.
						Пустой выбор = все разделы.
					</span>
				</span>
			</div>
			<span class="titlo-section-ms" id="titlo-section-ms">
				<button type="button" class="adm-btn titlo-section-ms__btn" id="titlo-section-ms-btn"
					aria-haspopup="listbox" aria-expanded="false"><?= htmlspecialcharsbx($btnLabel) ?></button>
				<div class="titlo-section-ms__panel" id="titlo-section-ms-panel" hidden>
					<input type="text" id="titlo-auto-section-q" class="adm-input titlo-section-ms__search"
						placeholder="части слов: отос kaw" autocomplete="off" spellcheck="false"
						aria-label="Поиск раздела по частям слов">
					<div class="titlo-section-ms__hint">Части слов в любом порядке · список разделов ниже</div>
					<div class="titlo-section-ms__list" id="titlo-section-ms-list" role="listbox" aria-multiselectable="true">
						<?php foreach ($tree as $sec): ?>
							<?php
							$sid = (int) $sec['id'];
							$checked = isset($selectedMap[$sid]);
							?>
							<label class="titlo-section-ms__item" data-name="<?= htmlspecialcharsbx($sec['name']) ?>">
								<input type="checkbox" class="titlo-section-ms__cb" value="<?= $sid ?>"
									<?= $checked ? 'checked' : '' ?>>
								<span><?= htmlspecialcharsbx($sec['label']) ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="titlo-section-ms__footer">
						<button type="button" class="adm-btn" id="titlo-section-ms-select-visible" title="Отметить все разделы в списке ниже (с учётом поиска)">Выбрать все</button>
						<button type="button" class="adm-btn" id="titlo-section-ms-clear">Очистить</button>
						<button type="button" class="adm-btn-save" id="titlo-section-ms-apply">Применить</button>
					</div>
				</div>
			</span>
		</div>
		<?php
		self::renderSectionBranchFilterScript();
	}

	/**
	 * JS API: TitloSectionBranch.getIds() / .bind(onChange) / .close()
	 */
	public static function renderSectionBranchFilterScript(): void
	{
		if (self::$sectionBranchJsDone) {
			return;
		}
		self::$sectionBranchJsDone = true;
		?>
		<script>
		(function () {
			if (window.TitloSectionBranch) return;

			function normalizeSearch(s) {
				return String(s || '').toLowerCase().replace(/ё/g, 'е').trim();
			}

			function searchTokens(q) {
				var parts = normalizeSearch(q).split(/[\s,;|]+/);
				var out = [];
				for (var i = 0; i < parts.length; i++) {
					if (parts[i].length >= 2) out.push(parts[i]);
				}
				return out;
			}

			function itemHaystack(item) {
				var name = item.getAttribute('data-name') || '';
				var label = item.textContent || '';
				return normalizeSearch(name + ' ' + label);
			}

			function tokensMatch(hay, tokens, modeAnd) {
				if (!tokens.length) return true;
				if (modeAnd) {
					for (var i = 0; i < tokens.length; i++) {
						if (hay.indexOf(tokens[i]) === -1) return false;
					}
					return true;
				}
				for (var j = 0; j < tokens.length; j++) {
					if (hay.indexOf(tokens[j]) !== -1) return true;
				}
				return false;
			}

			function root() {
				return document.getElementById('titlo-section-ms');
			}

			function panel() {
				return document.getElementById('titlo-section-ms-panel');
			}

			function btn() {
				return document.getElementById('titlo-section-ms-btn');
			}

			function getIds() {
				var ids = [];
				var list = document.getElementById('titlo-section-ms-list');
				if (!list) return ids;
				Array.prototype.forEach.call(list.querySelectorAll('.titlo-section-ms__cb:checked'), function (cb) {
					var id = parseInt(cb.value, 10) || 0;
					if (id > 0) ids.push(id);
				});
				return ids;
			}

			function updateBtn() {
				var b = btn();
				if (!b) return;
				var n = getIds().length;
				b.textContent = n === 0 ? 'Все разделы' : ('Выбрано: ' + n);
			}

			function setOpen(open) {
				var p = panel();
				var b = btn();
				if (!p || !b) return;
				if (open) {
					p.hidden = false;
					b.setAttribute('aria-expanded', 'true');
					var q = document.getElementById('titlo-auto-section-q');
					if (q) {
						q.focus();
						q.select();
					}
					filterList();
				} else {
					p.hidden = true;
					b.setAttribute('aria-expanded', 'false');
				}
			}

			function filterList() {
				var qEl = document.getElementById('titlo-auto-section-q');
				var list = document.getElementById('titlo-section-ms-list');
				if (!qEl || !list) return;
				var tokens = searchTokens(qEl.value);
				var items = list.querySelectorAll('.titlo-section-ms__item');
				var andHits = 0;
				Array.prototype.forEach.call(items, function (item) {
					var hay = itemHaystack(item);
					var ok = tokensMatch(hay, tokens, true);
					item.hidden = !ok;
					if (ok) andHits++;
				});
				// если все слова сразу нигде не встретились — покажем разделы по любому слову
				if (tokens.length > 1 && andHits === 0) {
					Array.prototype.forEach.call(items, function (item) {
						item.hidden = !tokensMatch(itemHaystack(item), tokens, false);
					});
				}
				var empty = list.querySelector('.titlo-section-ms__empty');
				var visible = list.querySelector('.titlo-section-ms__item:not([hidden])');
				if (!visible) {
					if (!empty) {
						empty = document.createElement('div');
						empty.className = 'titlo-section-ms__empty';
						empty.textContent = 'Нет разделов по запросу';
						list.appendChild(empty);
					}
					empty.hidden = false;
				} else if (empty) {
					empty.hidden = true;
				}
			}

			var changeCb = null;
			var snapshot = [];

			function notify() {
				updateBtn();
				if (typeof changeCb === 'function') changeCb(getIds());
			}

			function bind(onChange) {
				changeCb = onChange;
				var r = root();
				if (!r || r.getAttribute('data-bound') === '1') {
					updateBtn();
					return;
				}
				r.setAttribute('data-bound', '1');
				updateBtn();

				var b = btn();
				if (b) {
					b.addEventListener('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						var p = panel();
						var willOpen = !p || p.hidden;
						if (willOpen) {
							snapshot = getIds().slice();
						}
						setOpen(willOpen);
					});
				}

				var q = document.getElementById('titlo-auto-section-q');
				if (q) {
					q.addEventListener('input', filterList);
					q.addEventListener('keydown', function (e) {
						if (e.key === 'Escape') {
							e.preventDefault();
							setOpen(false);
						}
						if (e.key === 'Enter') {
							e.preventDefault();
							var list = document.getElementById('titlo-section-ms-list');
							if (!list) return;
							var first = list.querySelector('.titlo-section-ms__item:not([hidden]) .titlo-section-ms__cb');
							if (first) {
								first.checked = !first.checked;
							}
						}
					});
				}

				var selectVisible = document.getElementById('titlo-section-ms-select-visible');
				if (selectVisible) {
					selectVisible.addEventListener('click', function (e) {
						e.preventDefault();
						var list = document.getElementById('titlo-section-ms-list');
						if (!list) return;
						Array.prototype.forEach.call(list.querySelectorAll('.titlo-section-ms__item:not([hidden]) .titlo-section-ms__cb'), function (cb) {
							cb.checked = true;
						});
						updateBtn();
					});
				}

				var apply = document.getElementById('titlo-section-ms-apply');
				if (apply) {
					apply.addEventListener('click', function (e) {
						e.preventDefault();
						setOpen(false);
						notify();
					});
				}

				var clear = document.getElementById('titlo-section-ms-clear');
				if (clear) {
					clear.addEventListener('click', function (e) {
						e.preventDefault();
						var list = document.getElementById('titlo-section-ms-list');
						if (list) {
							Array.prototype.forEach.call(list.querySelectorAll('.titlo-section-ms__cb'), function (cb) {
								cb.checked = false;
							});
						}
						if (q) q.value = '';
						filterList();
						updateBtn();
					});
				}

				document.addEventListener('click', function (e) {
					var p = panel();
					if (!p || p.hidden) return;
					if (r.contains(e.target)) return;
					setOpen(false);
					var now = getIds();
					var same = now.length === snapshot.length && now.every(function (id, i) { return id === snapshot[i]; });
					if (!same) notify();
				});

				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') setOpen(false);
				});
			}

			window.TitloSectionBranch = {
				getIds: getIds,
				bind: bind,
				close: function () { setOpen(false); },
				updateLabel: updateBtn
			};
		})();
		</script>
		<?php
	}
}
