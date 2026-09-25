<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\CatalogRepository;
use Titlo\Relevance\Config;
use Titlo\Relevance\UserFields;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');
UserFields::ensurePhraseField();

$entityType = (defined('TITLO_NAMES_ENTITY') && TITLO_NAMES_ENTITY === 'S')
	|| (isset($_GET['ENTITY']) && strtoupper((string) $_GET['ENTITY']) === 'S')
	? 'S'
	: 'E';
$isSection = $entityType === 'S';
$entityLabel = $isSection ? 'категорий' : 'товаров';
$entityLabelOne = $isSection ? 'категорию' : 'товар';
$iblockEdit = $isSection
	? '/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=' . (int) Config::iblockId() . '&type=catalog&ID='
	: '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . (int) Config::iblockId() . '&type=catalog&ID=';
$singleEntity = $isSection ? 'S' : 'E';

$APPLICATION->SetTitle('Titlo: проверка названий ' . $entityLabel);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
$filter = (string) ($_GET['filter'] ?? 'todo');
$q = (string) ($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$phraseMaxLen = (int) UserFields::PHRASE_MAX_LEN;
$sectionIds = CatalogRepository::normalizeSectionIds(
	$_GET['section_ids'] ?? ($_GET['section_id'] ?? [])
);
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-names">
	<?php if (!$hasKey): ?>
		<div class="adm-info-message-wrap adm-info-message-red">
			<div class="adm-info-message">
				Укажите API-ключ в
				<a href="/bitrix/admin/settings.php?lang=<?= LANGUAGE_ID ?>&mid=titlo.relevance">настройках модуля</a>.
			</div>
		</div>
	<?php endif; ?>

	<details class="titlo-howto">
		<summary>
			Как работает проверка названий <?= $isSection ? 'категорий' : 'товаров' ?>
		</summary>
		<div class="titlo-howto__body">
			<ul>
				<li>
					Ключевая фраза пишется в
					<code><?= htmlspecialcharsbx(UserFields::PHRASE_FIELD) ?></code>
					(стандартные промпты ≈50 символов; свой промпт — до <?= $phraseMaxLen ?>)
					и подставляется в «Проработку» вместо длинного NAME.
				</li>
				<li>
					<strong>Свой промпт</strong> — в «Промптах» задайте расширенные требования,
					выберите его здесь и сгенерируйте заново: ответ не режется жёстко на 50.
				</li>
				<li>
					<strong>Сгенерировать</strong> — сразу пишет фразу в UF.
					<strong>Сохранить</strong> — если правите руками.
					<strong>В название</strong> — подставляет короткую фразу в поле NAME
					(оригинал сохраняется в <code><?= htmlspecialcharsbx(UserFields::NAME_ORIG_FIELD) ?></code>,
					можно <strong>Вернуть NAME</strong>).
					<strong>Сбросить</strong> — очищает фразу, позиция снова в «Нужно проработать».
				</li>
				<li>
					Фильтр <strong>«Проработанные»</strong> — <?= $isSection ? 'категории' : 'товары' ?> с короткой фразой.
					<strong>«Нужно проработать»</strong> — длинные без фразы.
				</li>
				<li>
					<strong>Сгенерировать по фильтру</strong> — в очередь ставится не больше
					<strong>лимита за запуск</strong> (по умолчанию <?= (int) \Titlo\Relevance\PhraseBulk::DEFAULT_LIMIT ?>,
					максимум <?= (int) \Titlo\Relevance\PhraseBulk::MAX_LIMIT ?>), даже если в фильтре тысяч позиций.
					Сколько именно уйдёт в работу — видно рядом с лимитом и на кнопке.
					К API — волнами по <?= (int) \Titlo\Relevance\PhraseBulk::API_CHUNK ?>.
					<strong>Остановить генерацию</strong> — сразу прерывает текущую пачку.
					Пока вкладка открыта — быстрее; агент Bitrix тоже крутит.
				</li>
				<li>
					Поиск: ID, название, код, <strong>полный URL</strong> или
					<strong>части слов в любом порядке</strong>
					(например <code>kaw oto</code>).
				</li>
				<li>
					<strong>Ветка каталога</strong> (мятный блок) — поиск разделов по частям слов,
					«Выбрать все» по видимым, «Применить». Несколько веток сразу;
					пустой выбор = весь каталог. Список и пачка «Сгенерировать» смотрят только на выбранную ветку.
				</li>
				<li>
					<strong>Список</strong> (синий блок рядом) — фильтр статуса фразы + поле поиска.
				</li>
				<li>
					Галочка <strong>«не надо»</strong>
					(<code><?= htmlspecialcharsbx(UserFields::SKIP_FIELD) ?></code>)
					убирает из рабочих фильтров.
				</li>
				<li>
					Фильтр <strong>«Страна в названии»</strong> — Германия/США/China и т.п. (склонения и EN).
				</li>
				<?php if ($isSection): ?>
					<li>
						Отдельный промпт категорий: слово <strong>«купить»</strong> всегда
						<strong>в конце</strong> фразы — даже если его нет в полном названии.
					</li>
				<?php endif; ?>
			</ul>
		</div>
	</details>

	<section class="titlo-panel titlo-panel--select" aria-labelledby="titlo-panel-select-title">
		<header class="titlo-panel__head">
			<h3 class="titlo-panel__title" id="titlo-panel-select-title">Отбор <?= $isSection ? 'категорий' : 'товаров' ?></h3>
			<span class="titlo-panel__hint">Ветка каталога, фильтр и поиск — кого берём в работу</span>
		</header>
		<div class="titlo-panel__body">
			<div class="filters">
				<?php AdminUi::renderSectionBranchFilter($sectionIds, $isSection ? 'категории' : 'товары'); ?>
				<div class="titlo-list-scope">
					<div class="titlo-list-scope__head">
						<span class="titlo-list-scope__title">Список</span>
					</div>
					<div class="titlo-list-scope__row">
						<label>Фильтр:
							<select id="titlo-filter">
								<option value="todo" <?= $filter === 'todo' ? 'selected' : '' ?>>Нужно проработать (длинные без фразы)</option>
								<option value="filled" <?= $filter === 'filled' ? 'selected' : '' ?>>Проработанные (есть короткая фраза)</option>
								<option value="country_todo" <?= $filter === 'country_todo' ? 'selected' : '' ?>>Страна в названии — без фразы</option>
								<option value="country" <?= $filter === 'country' ? 'selected' : '' ?>>Страна в названии (все)</option>
								<option value="empty" <?= $filter === 'empty' ? 'selected' : '' ?>>Без короткой фразы</option>
								<option value="long" <?= $filter === 'long' ? 'selected' : '' ?>>NAME длиннее 50</option>
								<option value="skip" <?= $filter === 'skip' ? 'selected' : '' ?>>Не прорабатывать</option>
								<option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Все</option>
							</select>
						</label>
						<input type="text" id="titlo-q" class="adm-input" placeholder="ID / название / код / URL" value="<?= htmlspecialcharsbx($q) ?>" style="width:280px"
							title="Можно вставить полный URL страницы товара или категории">
						<input type="button" id="titlo-reload" class="adm-btn" value="Показать">
						<span id="titlo-list-status" class="status"></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="titlo-panel titlo-panel--run" aria-labelledby="titlo-panel-run-title">
		<header class="titlo-panel__head">
			<h3 class="titlo-panel__title" id="titlo-panel-run-title">Генерация коротких фраз</h3>
			<span class="titlo-panel__hint">Промпты и пачка по текущему отбору</span>
		</header>
		<div class="titlo-panel__body bulk-bar">
		<label>Промпт (строка)
			<span class="titlo-help" tabindex="0" aria-label="Что такое промпт строки">?
				<span class="titlo-help__tip"><?= $isSection
					? 'Для кнопки «Сгенерировать» в строке: одна категория → одна короткая фраза, бренд из названия сохраняем, «купить» в конце. Текст правите в «Промпты» → «Короткие названия категорий».'
					: 'Для кнопки «Сгенерировать» в каждой строке таблицы: один товар → одна короткая фраза. Текст промпта правите в разделе «Промпты» → «Короткая фраза (один товар)».'
				?></span>
			</span>:
			<select id="titlo-prompt-phrase" class="adm-input" style="min-width:240px"></select>
		</label>
		<span id="titlo-prompt-phrase-meta" class="bulk-status" style="min-width:160px"></span>
		<label>Промпт (пачка)
			<span class="titlo-help" tabindex="0" aria-label="Что такое промпт пачки">?
				<span class="titlo-help__tip"><?= $isSection
					? 'Для «Сгенерировать по фильтру»: много категорий за раз. В каждой фразе «купить» в конце. Текст — «Промпты» → «Короткие фразы категорий (пачка)».'
					: 'Для кнопки «Сгенерировать по фильтру»: много товаров за раз (волнами). Ответ — JSON-массив фраз. Текст промпта — в «Промпты» → «Короткие фразы (пачка)».'
				?></span>
			</span>:
			<select id="titlo-prompt-batch" class="adm-input" style="min-width:240px"></select>
		</label>
		<span id="titlo-prompt-batch-meta" class="bulk-status" style="min-width:160px"></span>
		<label>Лимит за запуск
			<span class="titlo-help" tabindex="0" aria-label="Лимит за запуск">?
				<span class="titlo-help__tip">
					Сколько <?= $isSection ? 'категорий' : 'товаров' ?> взять в <b>одну</b> пачку из текущего фильтра.
					Если в фильтре 19&nbsp;000, а лимит 200 — обработаются только <b>200</b>, остальные не трогаем.
					Максимум за запуск: <?= (int) \Titlo\Relevance\PhraseBulk::MAX_LIMIT ?>.
					К API — по <?= (int) \Titlo\Relevance\PhraseBulk::API_CHUNK ?> за запрос.
				</span>
			</span>:
			<input type="number" id="titlo-bulk-limit" class="adm-input" value="<?= (int) \Titlo\Relevance\PhraseBulk::DEFAULT_LIMIT ?>" min="1" max="<?= (int) \Titlo\Relevance\PhraseBulk::MAX_LIMIT ?>" style="width:90px">
		</label>
		<span id="titlo-bulk-plan" class="titlo-bulk-plan" aria-live="polite"></span>
		<input type="button" id="titlo-bulk-start" class="adm-btn-save" value="Сгенерировать по фильтру" <?= $hasKey ? '' : 'disabled' ?>>
		<input type="button" id="titlo-bulk-stop" class="adm-btn titlo-bulk-stop" value="Остановить генерацию" disabled
			title="Прервать текущую пачку: оставшиеся в очереди не будут отправлены в API">
		<span id="titlo-bulk-status" class="bulk-status">Массовая генерация не запущена</span>
		</div>
	</section>

	<table>
		<thead>
		<tr>
			<th style="width:70px">ID</th>
			<th>Полное название</th>
			<th style="width:300px">Ключевая фраза
				<span class="titlo-help" tabindex="0" aria-label="Лимит фразы">?
					<span class="titlo-help__tip">
						Стандартные промпты ≈50 символов.<br>
						Свой промпт — свои правила, хранение до <?= $phraseMaxLen ?> (лимит NAME в Bitrix).<br>
						Счётчик под полем — текущая длина / максимум.
					</span>
				</span>
			</th>
			<th style="width:130px">Не прорабатывать</th>
			<th style="width:280px">Действия</th>
		</tr>
		</thead>
		<tbody id="titlo-names-body">
		<tr><td colspan="5">Загрузка…</td></tr>
		</tbody>
	</table>
	<div class="pager" id="titlo-pager"></div>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var page = <?= (int) $page ?>;
	var hasKey = <?= $hasKey ? 'true' : 'false' ?>;
	var entityType = <?= json_encode($entityType) ?>;
	var iblockEditBase = <?= json_encode($iblockEdit) ?>;
	var singleEntity = <?= json_encode($singleEntity) ?>;
	var bulkTimer = null;
	var bulkBusy = false;
	var bulkMax = <?= (int) \Titlo\Relevance\PhraseBulk::MAX_LIMIT ?>;
	var phraseMaxLen = <?= (int) $phraseMaxLen ?>;
	var listTotal = 0;
	var entityWord = <?= json_encode($isSection ? 'категорий' : 'товаров') ?>;

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.sessid = sessid;
		data.entity_type = entityType;
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Bitrix-Csrf-Token': sessid},
			body: new URLSearchParams(data).toString(),
			credentials: 'same-origin'
		}).then(function (r) { return r.json(); });
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
		});
	}

	function safeHref(u) {
		u = String(u || '').trim();
		if (!u) return '#';
		if (u.indexOf('//') === 0) return '#';
		if (u.charAt(0) === '/') return escapeHtml(u);
		if (/^https?:\/\//i.test(u)) return escapeHtml(u);
		return '#';
	}

	function currentBulkLimit() {
		var limit = parseInt(document.getElementById('titlo-bulk-limit').value, 10) || <?= (int) \Titlo\Relevance\PhraseBulk::DEFAULT_LIMIT ?>;
		if (limit < 1) limit = 1;
		if (limit > bulkMax) limit = bulkMax;
		return limit;
	}

	function plannedBulkCount() {
		var limit = currentBulkLimit();
		if (listTotal <= 0) return 0;
		return Math.min(limit, listTotal);
	}

	function updateBulkPlan() {
		var plan = document.getElementById('titlo-bulk-plan');
		var startBtn = document.getElementById('titlo-bulk-start');
		var limit = currentBulkLimit();
		var will = plannedBulkCount();
		if (!plan) return;
		if (listTotal <= 0) {
			plan.innerHTML = 'По фильтру: <b>0</b>. Сначала нажмите «Показать» или смените фильтр.';
			if (startBtn && !startBtn.disabled) {
				startBtn.value = 'Сгенерировать по фильтру';
			}
			return;
		}
		var left = Math.max(0, listTotal - will);
		var html = 'По фильтру: <b>' + listTotal + '</b> · за этот запуск: <b>' + will + '</b>';
		if (will < listTotal) {
			html += ' <span class="titlo-bulk-plan__cap">(лимит ' + limit + ', ещё ' + left + ' не войдут)</span>';
		} else {
			html += ' <span class="titlo-bulk-plan__all">(весь текущий фильтр)</span>';
		}
		plan.innerHTML = html;
		if (startBtn) {
			var stopBtn = document.getElementById('titlo-bulk-stop');
			var running = stopBtn && !stopBtn.disabled;
			if (!running) {
				startBtn.value = will > 0
					? ('Сгенерировать ' + will + (will < listTotal ? (' из ' + listTotal) : ''))
					: 'Сгенерировать по фильтру';
			}
		}
	}

	function bulkStatusLabel(st) {
		st = String(st || '');
		if (st === 'running') return 'идёт';
		if (st === 'stopped') return 'остановлена';
		if (st === 'done') return 'готово';
		if (st === 'queued') return 'в очереди';
		return st;
	}

	function currentSectionIds() {
		if (window.TitloSectionBranch && typeof TitloSectionBranch.getIds === 'function') {
			return TitloSectionBranch.getIds();
		}
		return [];
	}

	function load() {
		var filter = document.getElementById('titlo-filter').value;
		var q = document.getElementById('titlo-q').value;
		document.getElementById('titlo-list-status').textContent = 'Загрузка…';
		post('list_names', {
			filter: filter,
			q: q,
			page: page,
			page_size: 25,
			section_ids: currentSectionIds().join(',')
		}).then(function (res) {
			var body = document.getElementById('titlo-names-body');
			if (!res.ok) {
				body.innerHTML = '<tr><td colspan="5">' + escapeHtml(res.error || 'Ошибка') + '</td></tr>';
				document.getElementById('titlo-list-status').textContent = '';
				listTotal = 0;
				updateBulkPlan();
				return;
			}
			listTotal = parseInt(res.total, 10) || 0;
			document.getElementById('titlo-list-status').textContent = 'Всего: ' + listTotal;
			updateBulkPlan();
			if (!res.items || !res.items.length) {
				body.innerHTML = '<tr><td colspan="5">Ничего не найдено</td></tr>';
			} else {
				body.innerHTML = res.items.map(function (item) {
					var warn = item.name_len > 50 && !item.titlo_phrase && !item.skip ? ' warn' : '';
					var ok = item.titlo_phrase ? ' ok' : '';
					var skipped = item.skip ? ' style="opacity:.65"' : '';
					var hasOrig = !!(item.name_orig && String(item.name_orig).trim());
					var nameBlock = '<div class="name-full' + warn + '">' + escapeHtml(item.name) +
						(item.has_country ? ' <span class="badge-country">страна</span>' : '') +
						'</div>' +
						'<div class="name-len">' + item.name_len + ' симв. · <a href="' + safeHref(item.url) + '" target="_blank" rel="noopener">на сайте</a></div>';
					if (hasOrig) {
						nameBlock += '<div class="name-orig" title="Оригинал до замены фразой">было: ' + escapeHtml(item.name_orig) + '</div>';
					}
					return '<tr data-id="' + item.id + '" data-name-orig="' + escapeHtml(item.name_orig || '') + '"' + skipped + '>' +
						'<td><a href="' + iblockEditBase + item.id + '&lang=<?= LANGUAGE_ID ?>" target="_blank">' + item.id + '</a></td>' +
						'<td>' + nameBlock + '</td>' +
						'<td><input type="text" class="adm-input phrase-input" maxlength="' + phraseMaxLen + '" value="' + escapeHtml(item.titlo_phrase || '') + '"' + (item.skip ? ' disabled' : '') + '>' +
						'<div class="name-len phrase-len' + ok + '">' + (item.titlo_phrase ? item.phrase_len : 0) + '/' + phraseMaxLen + '</div>' +
						'<div class="phrase-at">' + (item.phrase_at ? ('проработано ' + escapeHtml(item.phrase_at)) : '') + '</div></td>' +
						'<td><label><input type="checkbox" class="titlo-skip"' + (item.skip ? ' checked' : '') + '> не надо</label></td>' +
						'<td class="row-actions">' +
						'<input type="button" class="adm-btn titlo-gen" value="Сгенерировать"' + (hasKey && !item.skip ? '' : ' disabled') + '>' +
						'<input type="button" class="adm-btn-save titlo-save" value="Сохранить">' +
						'<input type="button" class="adm-btn titlo-apply-name" value="В название"' + (item.titlo_phrase ? '' : ' disabled') + ' title="Подставить короткую фразу в NAME (оригинал сохранится)">' +
						'<input type="button" class="adm-btn titlo-restore-name" value="Вернуть NAME"' + (hasOrig ? '' : ' disabled') + ' title="Вернуть оригинальное название из бэкапа">' +
						'<input type="button" class="adm-btn titlo-reset" value="Сбросить"' + (item.titlo_phrase ? '' : ' disabled') + ' title="Очистить короткую фразу — вернуть в «Нужно проработать»">' +
						'<a class="adm-btn" href="titlo_relevance_single.php?lang=<?= LANGUAGE_ID ?>&ENTITY=' + singleEntity + '&ID=' + item.id + '">Проработка</a>' +
						'<div class="status row-status" aria-live="polite"></div></td></tr>';
				}).join('');
			}

			var pages = res.pages || 1;
			var pager = document.getElementById('titlo-pager');
			pager.innerHTML = '';
			if (pages > 1) {
				for (var i = 1; i <= pages && i <= 30; i++) {
					(function (p) {
						var btn = document.createElement('input');
						btn.type = 'button';
						btn.value = String(p);
						btn.className = 'adm-btn' + (p === res.page ? ' adm-btn-save' : '');
						btn.style.marginRight = '4px';
						btn.onclick = function () { page = p; load(); };
						pager.appendChild(btn);
					})(i);
				}
			}

			Array.prototype.forEach.call(document.querySelectorAll('.phrase-input'), function (inp) {
				inp.addEventListener('input', function () {
					var len = inp.value.length;
					var el = inp.parentNode.querySelector('.phrase-len');
					if (el) el.textContent = len + '/' + phraseMaxLen;
					var tr = inp.closest('tr');
					if (!tr) return;
					var applyBtn = tr.querySelector('.titlo-apply-name');
					if (applyBtn) applyBtn.disabled = !inp.value.trim();
					var resetBtn = tr.querySelector('.titlo-reset');
					if (resetBtn) resetBtn.disabled = !inp.value.trim();
				});
			});
		});
	}

	function setRowStatus(tr, text, isError) {
		var status = tr.querySelector('.row-status');
		if (!status) return;
		if (status._clearTimer) {
			clearTimeout(status._clearTimer);
			status._clearTimer = null;
		}
		status.textContent = text || '';
		status.style.color = isError ? '#c00' : (text ? '#15803d' : '');
		if (text && !isError) {
			status._clearTimer = setTimeout(function () {
				status.textContent = '';
				status._clearTimer = null;
			}, 3500);
		}
	}

	function savePhrase(tr, opts) {
		opts = opts || {};
		var id = tr.getAttribute('data-id');
		var input = tr.querySelector('.phrase-input');
		var skipBox = tr.querySelector('.titlo-skip');
		setRowStatus(tr, opts.pendingText || 'Сохранение…', false);
		var status = tr.querySelector('.row-status');
		if (status) status.style.color = '#555';
		return post('save_phrase', {
			entity_id: id,
			phrase: input.value,
			skip: skipBox && skipBox.checked ? '1' : '0'
		}).then(function (res) {
			if (res.ok) {
				setRowStatus(tr, opts.okText || 'Сохранено', false);
				var atEl = tr.querySelector('.phrase-at');
				if (atEl) {
					atEl.textContent = res.phrase_at ? ('проработано ' + res.phrase_at) : '';
				}
				if (typeof res.phrase === 'string' && input) {
					input.value = res.phrase;
					var el = tr.querySelector('.phrase-len');
					if (el) {
						el.textContent = (res.phrase ? res.phrase.length : 0) + '/' + phraseMaxLen;
						el.className = 'name-len phrase-len' + (res.phrase ? ' ok' : '');
					}
					var resetBtn = tr.querySelector('.titlo-reset');
					if (resetBtn) resetBtn.disabled = !res.phrase;
					var applyBtn = tr.querySelector('.titlo-apply-name');
					if (applyBtn) applyBtn.disabled = !res.phrase;
				}
			} else {
				setRowStatus(tr, res.error || 'Ошибка сохранения', true);
			}
			if (res.ok && opts.reload !== false) load();
			return res;
		});
	}

	function updateNameCell(tr, name, nameOrig) {
		var td = tr.querySelector('td:nth-child(2)');
		if (!td) return;
		name = String(name || '');
		nameOrig = String(nameOrig || '').trim();
		tr.setAttribute('data-name-orig', nameOrig);
		var urlA = td.querySelector('a[href]');
		var url = urlA ? urlA.getAttribute('href') : '#';
		var warn = name.length > 50 ? ' warn' : '';
		var html = '<div class="name-full' + warn + '">' + escapeHtml(name) + '</div>' +
			'<div class="name-len">' + name.length + ' симв. · <a href="' + safeHref(url) + '" target="_blank" rel="noopener">на сайте</a></div>';
		if (nameOrig) {
			html += '<div class="name-orig" title="Оригинал до замены фразой">было: ' + escapeHtml(nameOrig) + '</div>';
		}
		td.innerHTML = html;
		var restoreBtn = tr.querySelector('.titlo-restore-name');
		if (restoreBtn) restoreBtn.disabled = !nameOrig;
	}

	function fmtBulk(batch) {
		if (!batch) return 'Массовая генерация не запущена';
		return 'Пачка #' + batch.id + ': ' + bulkStatusLabel(batch.status) +
			' · готово ' + batch.done +
			' · ошибки ' + batch.failed +
			' · осталось ' + batch.left +
			' из ' + batch.total;
	}

	function setBulkUi(batch) {
		var st = document.getElementById('titlo-bulk-status');
		var startBtn = document.getElementById('titlo-bulk-start');
		var stopBtn = document.getElementById('titlo-bulk-stop');
		st.textContent = fmtBulk(batch);
		var running = batch && batch.status === 'running';
		startBtn.disabled = !hasKey || !!running;
		stopBtn.disabled = !running;
		if (!running) {
			updateBulkPlan();
		} else {
			startBtn.value = 'Генерация…';
		}
		if (running && !bulkTimer) startBulkLoop();
		if (!running && bulkTimer) {
			clearInterval(bulkTimer);
			bulkTimer = null;
			load();
		}
	}

	function startBulkLoop() {
		if (bulkTimer) return;
		bulkTimer = setInterval(function () {
			if (bulkBusy) return;
			bulkBusy = true;
			post('bulk_phrase_tick', {}).then(function (res) {
				bulkBusy = false;
				if (res.batch) setBulkUi(res.batch);
			}).catch(function () { bulkBusy = false; });
		}, 2500);
	}

	document.getElementById('titlo-bulk-start').onclick = function () {
		if (!hasKey) return;
		var filter = document.getElementById('titlo-filter').value;
		var q = document.getElementById('titlo-q').value;
		var limit = currentBulkLimit();
		document.getElementById('titlo-bulk-limit').value = String(limit);
		var will = plannedBulkCount();
		if (will <= 0) {
			alert('По текущему фильтру нечего генерировать. Нажмите «Показать» и убедитесь, что «Всего» > 0.');
			return;
		}
		var msg = 'Запустить генерацию коротких фраз?\n\n'
			+ 'По фильтру найдено: ' + listTotal + ' ' + entityWord + '\n'
			+ 'Будет обработано в этом запуске: ' + will
			+ (will < listTotal ? (' (лимит ' + limit + ', остальные ' + (listTotal - will) + ' не войдут)') : ' (весь фильтр)')
			+ '\nК API — волнами по <?= (int) \Titlo\Relevance\PhraseBulk::API_CHUNK ?>.\n\n'
			+ 'Вкладку лучше не закрывать — так быстрее. Агент Bitrix тоже крутит.\n'
			+ 'Остановить можно кнопкой «Остановить генерацию».';
		if (!confirm(msg)) {
			return;
		}
		document.getElementById('titlo-bulk-status').textContent = 'Ставим в очередь…';
		post('bulk_phrase_start', {
			filter: filter,
			q: q,
			limit: limit,
			prompt_id: (document.getElementById('titlo-prompt-batch') || {}).value || 0,
			section_ids: currentSectionIds().join(','),
			confirm: '1'
		}).then(function (res) {
			if (!res.ok) {
				document.getElementById('titlo-bulk-status').textContent = res.error || 'Не удалось стартовать';
				return;
			}
			var queued = res.total || will;
			var note = '';
			if (res.filter_total && res.filter_total > queued) {
				note = ' (из ' + res.filter_total + ' по фильтру)';
			} else if (listTotal > queued) {
				note = ' (из ' + listTotal + ' по фильтру)';
			}
			document.getElementById('titlo-bulk-status').textContent =
				'В очереди: ' + queued + note;
			setBulkUi({
				id: res.batch_id,
				status: 'running',
				done: 0,
				failed: 0,
				left: queued,
				total: queued
			});
			startBulkLoop();
			bulkBusy = true;
			post('bulk_phrase_tick', {}).then(function (r) {
				bulkBusy = false;
				if (r.batch) setBulkUi(r.batch);
			}).catch(function () { bulkBusy = false; });
		});
	};

	document.getElementById('titlo-bulk-stop').onclick = function () {
		var stopBtn = document.getElementById('titlo-bulk-stop');
		stopBtn.disabled = true;
		document.getElementById('titlo-bulk-status').textContent = 'Останавливаем…';
		post('bulk_phrase_stop', {}).then(function (res) {
			if (res.batch) setBulkUi(res.batch);
			else {
				document.getElementById('titlo-bulk-status').textContent = res.error || 'Остановлено';
				updateBulkPlan();
			}
		}).catch(function () {
			document.getElementById('titlo-bulk-status').textContent = 'Не удалось остановить';
			stopBtn.disabled = false;
		});
	};

	document.getElementById('titlo-bulk-limit').addEventListener('input', updateBulkPlan);
	document.getElementById('titlo-bulk-limit').addEventListener('change', updateBulkPlan);
	document.getElementById('titlo-filter').addEventListener('change', function () {
		page = 1;
		load();
	});
	if (window.TitloSectionBranch) {
		TitloSectionBranch.bind(function () {
			page = 1;
			load();
		});
	}

	post('bulk_phrase_status', {}).then(function (res) {
		if (res.batch) setBulkUi(res.batch);
		else updateBulkPlan();
	});

	document.getElementById('titlo-reload').onclick = function () { page = 1; load(); };
	document.getElementById('titlo-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { page = 1; load(); }
	});

	document.getElementById('titlo-names-body').addEventListener('click', function (e) {
		var btn = e.target;
		if (!btn.classList.contains('titlo-save')
			&& !btn.classList.contains('titlo-gen')
			&& !btn.classList.contains('titlo-reset')
			&& !btn.classList.contains('titlo-apply-name')
			&& !btn.classList.contains('titlo-restore-name')) return;
		var tr = btn.closest('tr');
		if (!tr) return;
		var id = tr.getAttribute('data-id');
		var input = tr.querySelector('.phrase-input');
		var skipBox = tr.querySelector('.titlo-skip');
		var status = tr.querySelector('.row-status');

		if (btn.classList.contains('titlo-apply-name')) {
			var phrase = (input && input.value || '').trim();
			if (!phrase) {
				setRowStatus(tr, 'Сначала укажите короткую фразу', true);
				return;
			}
			var nameEl = tr.querySelector('.name-full');
			var curName = nameEl ? nameEl.childNodes[0].textContent.trim() : '';
			if (!curName) curName = (nameEl && nameEl.textContent || '').replace(/\s*страна\s*$/, '').trim();
			if (!confirm(
				'Заменить NAME на короткую фразу?\n\n'
				+ 'Было: ' + curName + '\n'
				+ 'Станет: ' + phrase + '\n\n'
				+ 'Оригинал сохранится — можно вернуть кнопкой «Вернуть NAME».'
			)) {
				return;
			}
			setRowStatus(tr, 'Пишем в NAME…', false);
			post('apply_phrase_to_name', {entity_id: id, phrase: phrase}).then(function (res) {
				if (!res.ok) {
					setRowStatus(tr, res.error || 'Ошибка', true);
					return;
				}
				updateNameCell(tr, res.name || phrase, res.name_orig || '');
				if (typeof res.phrase === 'string' && input) {
					input.value = res.phrase;
					var el = tr.querySelector('.phrase-len');
					if (el) {
						el.textContent = (res.phrase ? res.phrase.length : 0) + '/' + phraseMaxLen;
						el.className = 'name-len phrase-len' + (res.phrase ? ' ok' : '');
					}
				}
				setRowStatus(tr, res.unchanged ? 'NAME уже совпадает' : 'NAME обновлён', false);
			});
			return;
		}

		if (btn.classList.contains('titlo-restore-name')) {
			var orig = (tr.getAttribute('data-name-orig') || '').trim();
			if (!orig) {
				setRowStatus(tr, 'Оригинал NAME не сохранён', true);
				return;
			}
			if (!confirm('Вернуть оригинальное название?\n\n' + orig)) {
				return;
			}
			setRowStatus(tr, 'Возвращаем NAME…', false);
			post('restore_original_name', {entity_id: id}).then(function (res) {
				if (!res.ok) {
					setRowStatus(tr, res.error || 'Ошибка', true);
					return;
				}
				updateNameCell(tr, res.name || orig, res.name_orig || orig);
				setRowStatus(tr, 'NAME возвращён', false);
			});
			return;
		}

		if (btn.classList.contains('titlo-reset')) {
			if (!confirm('Очистить короткую фразу и вернуть в непроработанные по названиям?')) {
				return;
			}
			status.textContent = 'Сброс…';
			status.style.color = '#555';
			post('clear_phrase', {entity_id: id}).then(function (res) {
				if (!res.ok) {
					status.textContent = res.error || 'Ошибка';
					status.style.color = '#c00';
					return;
				}
				if (input) input.value = '';
				var el = tr.querySelector('.phrase-len');
				if (el) {
					el.textContent = '0/' + phraseMaxLen;
					el.className = 'name-len phrase-len';
				}
				var atEl = tr.querySelector('.phrase-at');
				if (atEl) atEl.textContent = '';
				btn.disabled = true;
				var applyBtn = tr.querySelector('.titlo-apply-name');
				if (applyBtn) applyBtn.disabled = true;
				status.textContent = 'В непроработанных';
				status.style.color = '#15803d';
				load();
			});
			return;
		}

		if (btn.classList.contains('titlo-save')) {
			savePhrase(tr);
			return;
		}

		if (skipBox && skipBox.checked) {
			status.textContent = 'Снят с проработки';
			status.style.color = '#b45309';
			return;
		}

		status.textContent = 'Генерация…';
		var nameEl = tr.querySelector('.name-full');
		var name = nameEl ? nameEl.childNodes[0].textContent.trim() : '';
		if (!name) name = (nameEl && nameEl.textContent || '').replace(/\s*страна\s*$/, '').trim();
		var attempts = 0;
		var maxAttempts = 45; // ~90s
		post('generate_phrase', {
			entity_id: id,
			name: name,
			prompt_id: (document.getElementById('titlo-prompt-phrase') || {}).value || 0
		}).then(function poll(res) {
			if (!res.ok) {
				status.textContent = res.error || 'Ошибка';
				status.style.color = '#c00';
				return;
			}
			if (res.status === 'completed') {
				input.value = res.result || '';
				var el = tr.querySelector('.phrase-len');
				if (el) el.textContent = input.value.length + '/' + phraseMaxLen;
				if (!input.value) {
					status.textContent = 'Пустой ответ генерации';
					status.style.color = '#c00';
					return;
				}
				return savePhrase(tr, {
					pendingText: 'Сохраняем…',
					okText: 'Сохранено',
					reload: false
				});
			}
			if (res.status === 'failed') {
				status.textContent = res.error || 'Сбой генерации';
				status.style.color = '#c00';
				return;
			}
			attempts++;
			if (attempts >= maxAttempts) {
				status.textContent = 'Таймаут: очередь ai_generation не обработала job. Запустите queue:work --queue=ai_generation';
				status.style.color = '#c00';
				return;
			}
			status.textContent = 'Ожидание… (' + attempts + '/' + maxAttempts + ')';
			status.style.color = '#555';
			return new Promise(function (r) { setTimeout(r, 2000); })
				.then(function () { return post('poll_generate', {record_id: res.record_id}); })
				.then(poll);
		}).catch(function (err) {
			status.textContent = (err && err.message) ? err.message : 'Сеть/JS ошибка';
			status.style.color = '#c00';
		});
	});

	document.getElementById('titlo-names-body').addEventListener('change', function (e) {
		var box = e.target;
		if (!box.classList || !box.classList.contains('titlo-skip')) return;
		var tr = box.closest('tr');
		if (!tr) return;
		var id = tr.getAttribute('data-id');
		var status = tr.querySelector('.row-status');
		var input = tr.querySelector('.phrase-input');
		var gen = tr.querySelector('.titlo-gen');
		status.textContent = 'Сохранение…';
		post('save_skip', {entity_id: id, skip: box.checked ? '1' : '0'}).then(function (res) {
			status.textContent = res.ok ? (box.checked ? 'Не прорабатывать' : 'Вернули в работу') : (res.error || 'Ошибка');
			status.style.color = res.ok ? '#15803d' : '#c00';
			if (res.ok) {
				if (input) input.disabled = !!box.checked;
				if (gen) gen.disabled = !hasKey || !!box.checked;
				tr.style.opacity = box.checked ? '.65' : '';
			}
		});
	});

	function fillPromptSelect(selId, metaId, payload) {
		var sel = document.getElementById(selId);
		var meta = document.getElementById(metaId);
		if (!sel || !payload) return;
		var items = payload.items || [];
		var active = payload.active_id || 0;
		var last = payload.last;
		sel.innerHTML = '';
		items.forEach(function (it) {
			var opt = document.createElement('option');
			opt.value = String(it.id);
			opt.textContent = it.name + (it.is_default ? ' ★' : '') + ' — ' + (it.last_used_label || 'не использовался');
			if (it.id === active) opt.selected = true;
			sel.appendChild(opt);
		});
		if (meta) {
			meta.textContent = last
				? ('Последний: «' + last.name + '» · ' + last.last_used_label)
				: 'Ещё не запускали';
		}
		sel.onchange = function () {
			var it = null;
			for (var i = 0; i < items.length; i++) {
				if (String(items[i].id) === String(sel.value)) { it = items[i]; break; }
			}
			if (meta && it) meta.textContent = 'Выбран: «' + it.name + '» · был: ' + (it.last_used_label || 'никогда');
		};
	}

	post('list_prompts', {}).then(function (res) {
		if (!res.ok || !res.by_type) return;
		var phraseKey = entityType === 'S' ? 'section_phrase' : 'phrase';
		var batchKey = entityType === 'S' ? 'section_phrase_batch' : 'phrase_batch';
		fillPromptSelect('titlo-prompt-phrase', 'titlo-prompt-phrase-meta', res.by_type[phraseKey]);
		fillPromptSelect('titlo-prompt-batch', 'titlo-prompt-batch-meta', res.by_type[batchKey]);
	});

	load();
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
