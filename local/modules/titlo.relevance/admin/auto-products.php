<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Titlo\Relevance\AdminUi;
use Titlo\Relevance\BatchQueue;
use Titlo\Relevance\Config;
use Titlo\Relevance\Prompts;
use Titlo\Relevance\UserFields;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');
UserFields::ensurePhraseField();
BatchQueue::ensureSchema();
Prompts::ensureTables();
Config::migrateTlpMissingDefault300();

$APPLICATION->SetTitle('Titlo: автоматическая проработка товаров');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
$filter = (string) ($_GET['filter'] ?? 'todo');
$q = (string) ($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$policy = Config::getAutoOption('existing_policy', 'E', BatchQueue::POLICY_OVERWRITE);
if ($policy !== BatchQueue::POLICY_SKIP) {
	$policy = BatchQueue::POLICY_OVERWRITE;
}
$tlpMissing = (int) Config::getAutoOption('tlp_missing', 'E', '300');
$tlpDiff = (int) Config::getAutoOption('tlp_diff', 'E', '5');
$genPreview = Config::getAutoOption('gen_preview', 'E', 'N') === 'Y';
$runMode = Config::getAutoOption('run_mode', 'E', BatchQueue::MODE_FULL);
$runMode = BatchQueue::normalizeRunMode($runMode);
$sort = (string) ($_GET['sort'] ?? 'score_asc');
if ($sort !== 'id_desc') {
	$sort = 'score_asc';
}
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-names titlo-auto-products">
	<?php if (!$hasKey): ?>
		<div class="adm-info-message-wrap adm-info-message-red">
			<div class="adm-info-message">
				Укажите API-ключ в
				<a href="/bitrix/admin/settings.php?lang=<?= LANGUAGE_ID ?>&mid=titlo.relevance">настройках модуля</a>.
			</div>
		</div>
	<?php endif; ?>

	<details class="titlo-howto">
		<summary>Как работает автоматическая проработка товаров</summary>
		<div class="titlo-howto__body">
			<ul>
				<li>
					В списке — активные товары с короткой фразой
					(шаг «Проверка названий»). Без фразы в автомат не берём.
				</li>
				<li>
					<strong>В очередь</strong> — два режима: <em>только анализ</em> (балл в колонку, без генерации
					и без даты автопроработки) или <em>полная проработка</em> (анализ → TLP → описание → сохранение →
					повторный анализ → дата автопроработки).
				</li>
				<li>
					<strong>Полное описание</strong> — длинный текст карточки товара
					(поле «Детальное описание» в Bitrix). Колонка «Описание» — есть ли оно и сколько символов.
				</li>
				<li>
					Колонка <strong>Балл</strong> — последний анализ (ваш / рекомендуемый). Глазик раскрывает
					сводку, облака TF-IDF и TLP без перехода на «проработку».
				</li>
				<li>
					Если описание уже есть — «Переписать описание заново» или
					«Не трогать текст, только отметить».
				</li>
				<li>
					<strong>Верхняя таблица</strong> — товары. <strong>Внизу</strong> — журнал заданий.
					«Текст не писали» = анализ был, описание не меняли (оно уже стояло).
					Колонка «Автопроработка» — автомат уже закрыл карточку, а не «есть ли текст».
				</li>
				<li>
					Сценарий: фильтр «Уже есть балл» → отметили слабые → «Полная проработка» → «Переписать» → в очередь.
				</li>
				<li>
					<strong>Прогнать уже отмеченный товар снова</strong>: поиск по ID → галка →
					«Полная проработка» → «Переписать описание заново» → «В очередь».
					Дата автопроработки сбросится сразу.
				</li>
				<li>
					Очередь крутит агент модуля; пока вкладка открыта — статус обновляется чаще.
					В модель уходит до 80 слов TLP (первые ~40 обязательно).
				</li>
			</ul>
		</div>
	</details>

	<div class="filters">
		<label>Фильтр
			<span class="titlo-help" tabindex="0" aria-label="Фильтр списка">?
				<span class="titlo-help__tip">
					Все варианты — только товары с короткой фразой (без неё в автомат не берём).<br>
					<b>Ещё без автопроработки</b> — в колонке «Автопроработка» пусто: полный цикл ещё не отмечали.<br>
					<b>Уже есть балл анализа</b> — был анализ, цифры в «Балл» есть; дата автопроработки при этом может быть пустой.<br>
					<b>Прошли автопроработку</b> — стоит дата: либо сгенерировали текст, либо отметили без генерации (политика «описание уже есть»). Это не «есть текст в карточке», а именно отметка автомата.<br>
					<b>Все с короткой фразой</b> — весь пул, из которого можно ставить в очередь.
				</span>
			</span>:
			<select id="titlo-auto-filter">
				<option value="todo" <?= $filter === 'todo' ? 'selected' : '' ?>>Ещё без автопроработки</option>
				<option value="scored" <?= $filter === 'scored' ? 'selected' : '' ?>>Уже есть балл анализа</option>
				<option value="done" <?= $filter === 'done' ? 'selected' : '' ?>>Прошли автопроработку</option>
				<option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Все с короткой фразой</option>
			</select>
		</label>
		<label>Сортировка
			<span class="titlo-help" tabindex="0" aria-label="Сортировка">?
				<span class="titlo-help__tip"><b>Балл ↑</b> — сначала самые слабые по последнему анализу (удобно выбирать, кого прогонять дальше).<br><b>ID ↓</b> — свежие товары сверху.</span>
			</span>:
			<select id="titlo-auto-sort">
				<option value="score_asc" <?= $sort === 'score_asc' ? 'selected' : '' ?>>Балл ↑ (низкий сверху)</option>
				<option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>ID ↓</option>
			</select>
		</label>
		<input type="text" id="titlo-auto-q" class="adm-input" placeholder="ID / название / код" value="<?= htmlspecialcharsbx($q) ?>" style="width:260px">
		<input type="button" id="titlo-auto-reload" class="adm-btn" value="Показать">
		<span id="titlo-auto-list-status" class="status"></span>
	</div>

	<div class="bulk-bar">
		<label>Режим
			<span class="titlo-help" tabindex="0" aria-label="Режим очереди">?
				<span class="titlo-help__tip"><b>Только анализ</b> — считает балл и останавливается (удобно выбрать низкие).<br><b>Полная проработка</b> — анализ, генерация описания, сохранение, повторный анализ.<br><b>Повторная доработка</b> — без первого анализа: берёт последний history_id / TLP, генерирует по промпту доработки, сохраняет и снова считает балл. Без прошлого балла в очередь не ставит.</span>
			</span>:
			<select id="titlo-auto-run-mode" class="adm-input" style="min-width:180px">
				<option value="analyze_only" <?= $runMode === 'analyze_only' ? 'selected' : '' ?>>Только анализ</option>
				<option value="full" <?= $runMode === 'full' ? 'selected' : '' ?>>Полная проработка</option>
				<option value="refine" <?= $runMode === 'refine' ? 'selected' : '' ?>>Повторная доработка</option>
			</select>
		</label>
		<span class="titlo-auto-full-opts" id="titlo-auto-full-opts">
		<label><span id="titlo-auto-prompt-detail-label">Промпт полного описания</span>
			<span class="titlo-help" tabindex="0" aria-label="Промпт описания">?
				<span class="titlo-help__tip">Список зависит от режима: полная проработка / повторная доработка. Привязка режима — в «Промпты». К тексту дописываются TLP и HTML страницы.</span>
			</span>:
			<select id="titlo-auto-prompt-detail" class="adm-input titlo-prompt-select" data-type="detail" style="min-width:240px"></select>
		</label>
		<label style="white-space:nowrap">
			<input type="checkbox" id="titlo-auto-gen-preview" value="1" <?= $genPreview ? 'checked' : '' ?>>
			ещё анонс
			<span class="titlo-help" tabindex="0" aria-label="Анонс">?
				<span class="titlo-help__tip">Короткий PREVIEW_TEXT под карточкой/в списках. Отдельный промпт из «Промпты» → «Анонс».</span>
			</span>
		</label>
		<label id="titlo-auto-preview-wrap" style="<?= $genPreview ? '' : 'display:none' ?>">Промпт анонса:
			<select id="titlo-auto-prompt-preview" class="adm-input titlo-prompt-select" data-type="preview" style="min-width:200px"></select>
		</label>
		<label>Если описание уже есть
			<span class="titlo-help" tabindex="0" aria-label="Политика существующего описания">?
				<span class="titlo-help__tip">
					<b>Переписать описание заново</b> — анализ и новая генерация поверх текущего текста.<br>
					<b>Не трогать текст, только отметить</b> — описание не меняем, ставим дату автопроработки.
				</span>
			</span>:
			<select id="titlo-auto-policy" class="adm-input" style="min-width:220px">
				<option value="overwrite" <?= $policy === 'overwrite' ? 'selected' : '' ?>>Переписать описание заново</option>
				<option value="skip" <?= $policy === 'skip' ? 'selected' : '' ?>>Не трогать текст, только отметить</option>
			</select>
		</label>
		<label>TLP нет на сайте
			<span class="titlo-help" tabindex="0" aria-label="TLP нет на сайте">?
				<span class="titlo-help__tip">Сколько слов из таблицы «Нет на сайте» взять в генерацию (потолок в модели — 80).</span>
			</span>:
			<input type="number" id="titlo-auto-tlp-missing" class="adm-input" value="<?= (int) $tlpMissing ?>" min="0" max="500" style="width:70px">
		</label>
		<label>с разницей
			<span class="titlo-help" tabindex="0" aria-label="TLP с разницей">?
				<span class="titlo-help__tip">Сколько слов из таблицы «С разницей» добавить к списку для генерации.</span>
			</span>:
			<input type="number" id="titlo-auto-tlp-diff" class="adm-input" value="<?= (int) $tlpDiff ?>" min="0" max="200" style="width:70px">
		</label>
		</span>
		<input type="button" id="titlo-auto-enqueue" class="adm-btn-save" value="В очередь (0)" <?= $hasKey ? '' : 'disabled' ?>>
		<span id="titlo-auto-run-status" class="bulk-status">Очередь не запущена</span>
	</div>

	<table class="titlo-auto-table">
		<thead>
		<tr>
			<th style="width:36px"><input type="checkbox" id="titlo-auto-check-all" aria-label="Выбрать все на странице"></th>
			<th style="width:70px">ID</th>
			<th>Название</th>
			<th style="width:200px">Фраза
				<span class="titlo-help" tabindex="0" aria-label="Короткая фраза">?
					<span class="titlo-help__tip">Короткая фраза из «Проверки названий» — по ней идёт анализ релевантности.</span>
				</span>
			</th>
			<th style="width:100px">Описание
				<span class="titlo-help" tabindex="0" aria-label="Полное описание">?
					<span class="titlo-help__tip">Полное описание карточки (детальный текст). «есть · N» — уже заполнено, N символов без HTML. «пусто» — генерируем с нуля.</span>
				</span>
			</th>
			<th style="width:120px">Балл
				<span class="titlo-help" tabindex="0" aria-label="Балл анализа">?
					<span class="titlo-help__tip">Последний анализ: ваш балл / рекомендуемый. Глазик — сводка, облака TF-IDF и TLP.</span>
				</span>
			</th>
			<th style="width:140px">Автопроработка
				<span class="titlo-help" tabindex="0" aria-label="Автопроработка">?
					<span class="titlo-help__tip">Дата, когда автомат <b>закрыл</b> карточку: сгенерировал описание или отметил без генерации (политика «описание уже есть»). Пусто ≠ «нет текста» — текст может быть, а отметки ещё нет. Режим «Только анализ» сюда дату не пишет.</span>
				</span>
			</th>
			<th style="width:130px">Очередь
				<span class="titlo-help" tabindex="0" aria-label="Статус очереди">?
					<span class="titlo-help__tip">Текущий шаг задания: анализ, генерация, сохранение и т.д. «—» — не в очереди.</span>
				</span>
			</th>
			<th style="width:160px">Ссылки
				<span class="titlo-help" tabindex="0" aria-label="Ссылки">?
					<span class="titlo-help__tip"><b>Проработка</b> — точечная страница анализа и генерации по одному товару.<br><b>На сайте</b> — публичная карточка в новой вкладке.</span>
				</span>
			</th>
		</tr>
		</thead>
		<tbody id="titlo-auto-body">
		<tr><td colspan="9">Загрузка…</td></tr>
		</tbody>
	</table>
	<div class="pager" id="titlo-auto-pager"></div>

	<details class="titlo-howto titlo-auto-queue" open>
		<summary>Очередь автопроработки</summary>
		<div class="titlo-howto__body">
			<p style="margin:8px 0 0;font-size:12px;color:#64748b">
				Журнал заданий. Отметьте нужные строки и нажмите «Запустить выбранные» —
				режим и политика берутся из панели выше (например: было «только анализ» → поставить «Полная проработка» + «Переписать описание заново»).
			</p>
			<div style="margin:8px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
				<div id="titlo-auto-queue-counts" class="bulk-status"></div>
				<input type="button" id="titlo-auto-requeue-selected" class="adm-btn-save" value="Запустить выбранные (0)" <?= $hasKey ? '' : 'disabled' ?>>
				<input type="button" id="titlo-auto-queue-select-failed" class="adm-btn" value="Выбрать ошибки">
				<input type="button" id="titlo-auto-queue-select-no-text" class="adm-btn" value="Выбрать «текст не писали»">
				<span id="titlo-auto-requeue-status" class="bulk-status"></span>
			</div>
			<table>
				<thead>
				<tr>
					<th style="width:36px"><input type="checkbox" id="titlo-auto-queue-check-all" aria-label="Выбрать все в журнале"></th>
					<th style="width:50px">№</th>
					<th style="width:70px">Товар</th>
					<th>Фраза</th>
					<th style="width:90px">Режим</th>
					<th style="width:240px">Итог
						<span class="titlo-help" tabindex="0" aria-label="Итог задания">?
							<span class="titlo-help__tip">Результат задания. «Текст не писали» — анализ выполнен, описание не меняли. «Ошибка» — шаг не завершился.</span>
						</span>
					</th>
					<th style="width:80px">Анализ
						<span class="titlo-help" tabindex="0" aria-label="ID анализа">?
							<span class="titlo-help__tip">ID результата анализа в Titlo. Пусто — результат ещё не получен.</span>
						</span>
					</th>
					<th style="width:140px">Когда
						<span class="titlo-help" tabindex="0" aria-label="Время">?
							<span class="titlo-help__tip">Время последнего изменения статуса. Ниже мелким — время постановки в очередь.</span>
						</span>
					</th>
					<th>Ошибка</th>
				</tr>
				</thead>
				<tbody id="titlo-auto-queue-body">
				<tr><td colspan="9">Пока пусто</td></tr>
				</tbody>
			</table>
		</div>
	</details>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var page = <?= (int) $page ?>;
	var hasKey = <?= $hasKey ? 'true' : 'false' ?>;
	var entityType = 'E';
	var selected = {};
	var queueSelected = {};
	var lastBatchKey = '';
	var pollTimer = null;
	var expandCache = {};
	var COLSPAN = 9;

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.sessid = sessid;
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Bitrix-Csrf-Token': sessid},
			body: new URLSearchParams(data).toString(),
			credentials: 'same-origin'
		}).then(function (r) { return r.json(); });
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function safeHref(u) {
		u = String(u || '').trim();
		if (!u) return '#';
		if (u.indexOf('//') === 0) return '#';
		if (u.charAt(0) === '/') return escapeHtml(u);
		if (/^https?:\/\//i.test(u)) return escapeHtml(u);
		return '#';
	}

	function setStatus(id, text, isError) {
		var el = document.getElementById(id);
		if (!el) return;
		el.textContent = text || '';
		el.className = (id === 'titlo-auto-run-status' ? 'bulk-status' : 'status')
			+ (isError ? ' titlo-error' : '');
	}

	function selectedCount() {
		return Object.keys(selected).filter(function (k) { return selected[k]; }).length;
	}

	function updateEnqueueBtn() {
		var btn = document.getElementById('titlo-auto-enqueue');
		if (!btn) return;
		var n = selectedCount();
		btn.value = 'В очередь (' + n + ')';
		btn.disabled = !hasKey || n < 1;
	}

	function isAnalyzeOnly() {
		return document.getElementById('titlo-auto-run-mode').value === 'analyze_only';
	}

	function syncModeUi() {
		var mode = document.getElementById('titlo-auto-run-mode').value;
		var full = document.getElementById('titlo-auto-full-opts');
		if (full) full.classList.toggle('is-muted', mode === 'analyze_only');
		var lab = document.getElementById('titlo-auto-prompt-detail-label');
		if (lab) {
			lab.textContent = mode === 'refine' ? 'Промпт доработки' : 'Промпт полного описания';
		}
	}

	var promptLoadGen = 0;

	function loadPrompts() {
		var mode = document.getElementById('titlo-auto-run-mode').value;
		var filterMode = mode === 'analyze_only' ? 'full' : mode;
		var gen = ++promptLoadGen;
		return post('list_prompts', { run_mode: filterMode }).then(function (res) {
			if (gen !== promptLoadGen) return;
			if (!res.ok || !res.by_type) return;
			['detail', 'preview'].forEach(function (type) {
				var payload = res.by_type[type] || {};
				var items = payload.items || [];
				var active = payload.active_id || 0;
				var sel = document.querySelector('.titlo-prompt-select[data-type="' + type + '"]');
				if (!sel) return;
				var prev = sel.value;
				var prevInList = false;
				if (prev) {
					items.forEach(function (it) {
						if (String(it.id) === String(prev)) prevInList = true;
					});
				}
				// При смене режима старый id из другого списка не подходит —
				// берём active_id для текущего режима, а не «первый в списке».
				var pick = prevInList ? prev : (active || 0);
				sel.innerHTML = '';
				items.forEach(function (it) {
					var opt = document.createElement('option');
					opt.value = it.id;
					opt.textContent = it.name + (it.is_default ? ' ★' : '');
					if (pick && String(it.id) === String(pick)) opt.selected = true;
					sel.appendChild(opt);
				});
				if (!sel.value && sel.options.length) sel.selectedIndex = 0;
			});
			syncModeUi();
		});
	}

	function renderPager(total, pageSize, pages) {
		var host = document.getElementById('titlo-auto-pager');
		if (!host) return;
		if (pages <= 1) {
			host.innerHTML = 'Всего: ' + total;
			return;
		}
		var html = 'Стр. ' + page + ' / ' + pages + ' (всего ' + total + ') ';
		if (page > 1) html += '<a href="#" data-p="' + (page - 1) + '" class="titlo-auto-page">←</a> ';
		if (page < pages) html += '<a href="#" data-p="' + (page + 1) + '" class="titlo-auto-page">→</a>';
		host.innerHTML = html;
		Array.prototype.forEach.call(host.querySelectorAll('.titlo-auto-page'), function (a) {
			a.onclick = function (e) {
				e.preventDefault();
				page = parseInt(a.getAttribute('data-p'), 10) || 1;
				loadList();
			};
		});
	}

	function queueLabel(q) {
		if (!q) return '—';
		var st = q.status_label || q.status || '';
		var step = q.step_label || q.step || '';
		if (q.error && (q.status === 'failed' || q.status === 'ошибка')) {
			return '<span class="titlo-error">' + escapeHtml(st) + (step ? (' · ' + escapeHtml(step)) : '') + '</span>';
		}
		return escapeHtml(st) + (step ? (' · ' + escapeHtml(step)) : '');
	}

	function fmtScore(sc) {
		if (!sc || sc.points == null) return '—';
		var yours = escapeHtml(String(sc.points));
		if (sc.points_ideal == null || sc.points_ideal === '') return yours;
		return '<b>' + yours + '</b> / ' + escapeHtml(String(sc.points_ideal));
	}

	function scoreCell(it) {
		var sc = it.score;
		var hid = sc && sc.history_id ? sc.history_id : 0;
		var html = '<span class="titlo-auto-score">' + fmtScore(sc) + '</span>';
		if (hid) {
			html += ' <button type="button" class="titlo-auto-eye" data-id="' + it.id + '" data-hid="' + hid + '"' +
				' data-url="' + escapeHtml(it.url || '') + '"' +
				' data-phrase="' + escapeHtml(it.titlo_phrase || '') + '"' +
				' aria-label="Показать детали анализа" aria-expanded="false">' +
				'<span class="titlo-auto-eye__icon" aria-hidden="true"></span>' +
				'<span class="titlo-auto-eye__tip">Сводка анализа и TLP (облака — по кнопке)</span>' +
				'</button>';
		}
		return html;
	}

	function cloudTagsHtml(words, limit) {
		if (!Array.isArray(words) || !words.length) {
			return '<div class="titlo-auto-cloud-empty">нет слов</div>';
		}
		return '<div class="titlo-auto-cloud-tags">' + words.slice(0, limit || 40).map(function (w) {
			var text = w.word || w.text || '';
			var weight = w.weight != null ? w.weight : (w.tfidf != null ? w.tfidf : '');
			return '<span class="titlo-auto-cloud-tag">' + escapeHtml(text) +
				(weight !== '' ? ' <em>' + escapeHtml(String(weight).substring(0, 6)) + '</em>' : '') +
				'</span>';
		}).join('') + '</div>';
	}

	function tlpRowsHtml(list, take) {
		var rows = [];
		(list || []).slice(0, take).forEach(function (item) {
			rows.push(
				'<tr><td>' + escapeHtml(item.word) + '</td>' +
				'<td>' + (item.tfidf_top != null ? escapeHtml(Number(item.tfidf_top).toFixed(4)) : '—') + '</td>' +
				'<td>' + (item.avg_competitors != null ? escapeHtml(String(item.avg_competitors)) : '—') + '</td>' +
				'<td>' + (item.on_landing != null ? escapeHtml(String(item.on_landing)) : '—') + '</td>' +
				'<td>' + escapeHtml(String(item.suggested_count || 1)) + '</td></tr>'
			);
		});
		return rows.length ? rows.join('') : '<tr><td colspan="5">Нет фраз</td></tr>';
	}

	function expandHasTables(data) {
		var tlp = data.tlp || {};
		return ((tlp.missing || []).length + (tlp.diff || []).length) > 0;
	}

	function renderCloudsPanelHtml(clouds) {
		var comp = (clouds && clouds.competitors) || {};
		var land = (clouds && clouds.landing) || {};
		return '' +
			'<div class="titlo-auto-cloud-grid">' +
			'<div><div class="titlo-auto-cloud-title">Средние TF-IDF ссылок и текста конкурентов</div>' + cloudTagsHtml(comp.total) + '</div>' +
			'<div><div class="titlo-auto-cloud-title">TF-IDF ссылок и текста посадочной</div>' + cloudTagsHtml(land.total) + '</div>' +
			'<div><div class="titlo-auto-cloud-title">Средние TF-IDF текста конкурентов</div>' + cloudTagsHtml(comp.text) + '</div>' +
			'<div><div class="titlo-auto-cloud-title">TF-IDF текста посадочной</div>' + cloudTagsHtml(land.text) + '</div>' +
			'<div><div class="titlo-auto-cloud-title">Средние TF-IDF ссылок конкурентов</div>' + cloudTagsHtml(comp.links) + '</div>' +
			'<div><div class="titlo-auto-cloud-title">TF-IDF ссылок посадочной</div>' + cloudTagsHtml(land.links) + '</div>' +
			'</div>';
	}

	function renderExpandHtml(data) {
		var h = data.history || {};
		var hid = h.history_id || data.history_id || '';
		var pts = h.points != null ? h.points : (h.score != null ? h.score : null);
		var ideal = h.points_ideal != null ? h.points_ideal : null;
		var ptsHtml = pts == null ? '—' : (ideal != null
			? ('<b>' + escapeHtml(String(pts)) + '</b> / ' + escapeHtml(String(ideal)))
			: escapeHtml(String(pts)));
		var tlp = data.tlp || {};
		var missN = tlp.missing_total != null ? tlp.missing_total : (tlp.missing || []).length;
		var diffN = tlp.diff_total != null ? tlp.diff_total : (tlp.diff || []).length;
		var note = '';
		if (data.fallback_note) {
			note = '<div class="titlo-auto-expand-note">' + escapeHtml(data.fallback_note) + '</div>';
		} else if (!expandHasTables(data) && pts != null) {
			note = '<div class="titlo-auto-expand-note titlo-auto-expand-note--warn">' +
				'Балл есть, но TLP для этой проверки не сохранился. ' +
				'Поставьте товар снова в очередь в режиме «Только анализ».' +
				'</div>';
		}
		// Порядок как на titlo_relevance_single: баллы → кнопка облаков → TLP
		return '' +
			'<div class="titlo-auto-expand__inner" data-hid="' + escapeHtml(String(hid)) + '">' + note +
			'<div class="titlo-scores-box">' +
			'<div class="big">' + ptsHtml +
			' <span style="font-size:13px;font-weight:500;color:#64748b">ваш / рекомендуемый</span></div>' +
			'<div class="titlo-scores-meta">history_id=<b>' + escapeHtml(String(hid || '—')) + '</b>' +
			' · покрытие: <b>' + escapeHtml(h.coverage != null ? String(h.coverage) : '—') + '</b>' +
			' · плотность: <b>' + escapeHtml(h.density != null ? String(h.density) : '—') + '</b>' +
			' · позиция: <b>' + escapeHtml(h.position != null ? String(h.position) : '—') + '</b></div>' +
			'</div>' +
			'<div class="titlo-clouds-wrap titlo-auto-clouds-wrap">' +
			'<input type="button" class="adm-btn titlo-clouds-btn titlo-auto-clouds-btn" value="Облака TF-IDF посадочной и конкурентов">' +
			'<span class="titlo-help" tabindex="0" aria-label="Про облака TF-IDF">?' +
			'<span class="titlo-help__tip">Как на странице проработки: слева средние по ТОПу, справа — посадочная. Текст и ссылки отдельно.</span>' +
			'</span>' +
			'<div class="titlo-clouds-panel titlo-auto-clouds-panel"></div>' +
			'</div>' +
			'<h3 class="titlo-auto-expand__h3">Топ-лист фраз (TLP)</h3>' +
			'<div class="titlo-tlp-limits" style="display:block;margin:8px 0 10px;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px">' +
			'<span class="titlo-status">Нет на сайте: из ' + escapeHtml(String(missN)) + '</span> · ' +
			'<span class="titlo-status">С разницей: из ' + escapeHtml(String(diffN)) + '</span> · ' +
			'<span class="titlo-status">Сортировка: TF-IDF ТОП ↓</span>' +
			'</div>' +
			'<div class="titlo-phrases">' +
			'<div class="titlo-phrases__col"><h4>Нет на сайте</h4>' +
			'<div class="titlo-phrases__scroll"><table><thead><tr><th>Слово</th><th>TF-IDF ТОП</th><th>У конкурентов</th><th>На сайте</th><th>Ориентир</th></tr></thead>' +
			'<tbody>' + tlpRowsHtml(tlp.missing, 80) + '</tbody></table></div></div>' +
			'<div class="titlo-phrases__col"><h4>С разницей (добавить)</h4>' +
			'<div class="titlo-phrases__scroll"><table><thead><tr><th>Слово</th><th>TF-IDF ТОП</th><th>У конкурентов</th><th>На сайте</th><th>Ориентир</th></tr></thead>' +
			'<tbody>' + tlpRowsHtml(tlp.diff, 40) + '</tbody></table></div></div>' +
			'</div>' +
			'</div>';
	}

	function bindExpandClouds(host, data) {
		var btn = host.querySelector('.titlo-auto-clouds-btn');
		var panel = host.querySelector('.titlo-auto-clouds-panel');
		if (!btn || !panel) return;
		var hid = parseInt((data && data.history_id) || host.querySelector('.titlo-auto-expand__inner').getAttribute('data-hid'), 10) || 0;
		var cache = data.clouds || null;
		btn.onclick = function () {
			if (panel.classList.contains('is-open')) {
				panel.classList.remove('is-open');
				btn.value = 'Облака TF-IDF посадочной и конкурентов';
				return;
			}
			function openWith(clouds) {
				panel.innerHTML = renderCloudsPanelHtml(clouds);
				panel.classList.add('is-open');
				btn.disabled = false;
				btn.value = 'Скрыть облака TF-IDF';
			}
			if (cache && cache.competitors) {
				openWith(cache);
				return;
			}
			if (!hid) {
				panel.innerHTML = '<div class="titlo-error">Нет history_id</div>';
				panel.classList.add('is-open');
				return;
			}
			btn.disabled = true;
			btn.value = 'Загрузка облаков…';
			post('tfidf_clouds', {history_id: hid, limit: 100}).then(function (res) {
				btn.disabled = false;
				if (!res.ok) {
					btn.value = 'Облака TF-IDF посадочной и конкурентов';
					panel.innerHTML = '<div class="titlo-error">' + escapeHtml(res.error || 'Не удалось загрузить') + '</div>';
					panel.classList.add('is-open');
					return;
				}
				cache = res;
				if (data) data.clouds = res;
				openWith(res);
			}).catch(function (err) {
				btn.disabled = false;
				btn.value = 'Облака TF-IDF посадочной и конкурентов';
				panel.innerHTML = '<div class="titlo-error">' + escapeHtml((err && err.message) || 'Ошибка') + '</div>';
				panel.classList.add('is-open');
			});
		};
	}

	function closeExpand(exceptId) {
		Array.prototype.forEach.call(document.querySelectorAll('tr.titlo-auto-expand-row'), function (tr) {
			var pid = parseInt(tr.getAttribute('data-parent'), 10);
			if (exceptId && pid === exceptId) return;
			tr.parentNode.removeChild(tr);
		});
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-eye.is-open'), function (btn) {
			var id = parseInt(btn.getAttribute('data-id'), 10);
			if (exceptId && id === exceptId) return;
			btn.classList.remove('is-open');
			btn.setAttribute('aria-expanded', 'false');
		});
	}

	function fetchExpandBundle(hid) {
		// Облака не тянем сразу — только по кнопке, как на single.php
		return Promise.all([
			post('get_history', {history_id: hid}),
			post('missing_phrases', {history_id: hid, mode: 'tlp'})
		]).then(function (parts) {
			var hist = parts[0] || {};
			var tlp = parts[1] || {};
			if (!hist.ok && hist.error) {
				return Promise.reject(new Error(hist.error));
			}
			return {
				history_id: hid,
				history: hist,
				clouds: null,
				tlp: tlp.ok ? tlp : {}
			};
		});
	}

	function resolveExpandData(hid, url, phrase) {
		return fetchExpandBundle(hid).then(function (data) {
			if (expandHasTables(data)) {
				return data;
			}
			if (!url && !phrase) {
				return data;
			}
			return post('list_histories', {url: url || '', phrase: phrase || '', limit: 8}).then(function (list) {
				if ((!list || !list.ok) && phrase) {
					return post('list_histories', {url: '', phrase: phrase, limit: 8});
				}
				return list;
			}).then(function (list) {
				var items = (list && list.ok && list.items) ? list.items : [];
				var chain = Promise.resolve(null);
				items.forEach(function (it) {
					var alt = parseInt(it.history_id, 10) || 0;
					if (!alt || alt === hid) return;
					chain = chain.then(function (found) {
						if (found) return found;
						return fetchExpandBundle(alt).then(function (altData) {
							if (!expandHasTables(altData)) return null;
							altData.fallback_note = 'Показана полная проверка #' + alt +
								' (у #' + hid + ' таблицы не сохранились).';
							return altData;
						}).catch(function () { return null; });
					});
				});
				return chain.then(function (found) { return found || data; });
			}).catch(function () { return data; });
		});
	}

	function toggleExpand(btn) {
		var id = parseInt(btn.getAttribute('data-id'), 10);
		var hid = parseInt(btn.getAttribute('data-hid'), 10);
		var url = btn.getAttribute('data-url') || '';
		var phrase = btn.getAttribute('data-phrase') || '';
		var row = btn.closest('tr');
		if (!row || !hid) return;
		var existing = document.querySelector('tr.titlo-auto-expand-row[data-parent="' + id + '"]');
		if (existing) {
			existing.parentNode.removeChild(existing);
			btn.classList.remove('is-open');
			btn.setAttribute('aria-expanded', 'false');
			return;
		}
		closeExpand(id);
		btn.classList.add('is-open');
		btn.setAttribute('aria-expanded', 'true');
		var expandTr = document.createElement('tr');
		expandTr.className = 'titlo-auto-expand-row';
		expandTr.setAttribute('data-parent', String(id));
		expandTr.innerHTML = '<td colspan="' + COLSPAN + '"><div class="titlo-auto-expand">Загрузка…</div></td>';
		row.parentNode.insertBefore(expandTr, row.nextSibling);
		var host = expandTr.querySelector('.titlo-auto-expand');
		var cacheKey = hid + '|' + url + '|' + phrase;

		function paint(data) {
			host.innerHTML = renderExpandHtml(data);
			bindExpandClouds(host, data);
		}

		if (expandCache[cacheKey]) {
			paint(expandCache[cacheKey]);
			return;
		}

		resolveExpandData(hid, url, phrase).then(function (data) {
			expandCache[cacheKey] = data;
			if (data.history_id) {
				expandCache[data.history_id] = data;
			}
			if (expandTr.parentNode) paint(data);
		}).catch(function (err) {
			host.innerHTML = '<div class="titlo-error">' + escapeHtml((err && err.message) || 'Ошибка загрузки') + '</div>';
		});
	}

	function bindListEvents(body) {
		Array.prototype.forEach.call(body.querySelectorAll('.titlo-auto-row'), function (cb) {
			cb.onchange = function () {
				var id = parseInt(cb.value, 10);
				if (cb.checked) selected[id] = true;
				else delete selected[id];
				updateEnqueueBtn();
			};
		});
		Array.prototype.forEach.call(body.querySelectorAll('.titlo-auto-eye'), function (btn) {
			btn.onclick = function (e) {
				e.preventDefault();
				toggleExpand(btn);
			};
		});
	}

	function loadList() {
		setStatus('titlo-auto-list-status', 'Загрузка…');
		return post('auto_list_products', {
			page: page,
			page_size: 25,
			q: document.getElementById('titlo-auto-q').value,
			filter: document.getElementById('titlo-auto-filter').value,
			sort: document.getElementById('titlo-auto-sort').value
		}).then(function (res) {
			var body = document.getElementById('titlo-auto-body');
			if (!res.ok) {
				body.innerHTML = '<tr><td colspan="' + COLSPAN + '">' + escapeHtml(res.error || 'Ошибка') + '</td></tr>';
				setStatus('titlo-auto-list-status', res.error || 'Ошибка', true);
				return;
			}
			var rows = [];
			(res.items || []).forEach(function (it) {
				var checked = !!selected[it.id];
				rows.push(
					'<tr data-id="' + it.id + '">' +
					'<td><input type="checkbox" class="titlo-auto-row" value="' + it.id + '"' + (checked ? ' checked' : '') + '></td>' +
					'<td>' + it.id + '</td>' +
					'<td>' + escapeHtml(it.name) + '</td>' +
					'<td>' + escapeHtml(it.titlo_phrase) + '</td>' +
					'<td>' + (it.has_detail ? ('есть · ' + it.detail_chars) : 'пусто') + '</td>' +
					'<td class="titlo-auto-score-td">' + scoreCell(it) + '</td>' +
					'<td>' + (it.auto_done ? ('✓ ' + escapeHtml(it.auto_at)) : '—') + '</td>' +
					'<td>' + queueLabel(it.queue) + '</td>' +
					'<td><a href="' + escapeHtml(it.single_url) + '">проработка</a>' +
					(it.url ? (' · <a href="' + safeHref(it.url) + '" target="_blank" rel="noopener">на сайте</a>') : '') +
					'</td></tr>'
				);
			});
			body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="' + COLSPAN + '">Нет товаров по фильтру (нужна короткая фраза)</td></tr>';
			bindListEvents(body);
			renderPager(res.total || 0, res.page_size || 25, res.pages || 1);
			setStatus('titlo-auto-list-status', 'Найдено: ' + (res.total || 0));
			updateEnqueueBtn();
			document.getElementById('titlo-auto-check-all').checked = false;
		});
	}

	function updateQueueRequeueBtn() {
		var n = Object.keys(queueSelected).filter(function (k) { return queueSelected[k]; }).length;
		var btn = document.getElementById('titlo-auto-requeue-selected');
		if (btn) {
			btn.value = 'Запустить выбранные (' + n + ')';
			btn.disabled = !hasKey || n === 0;
		}
	}

	function formatQueueWhen(it) {
		var upd = (it.updated_at || '').replace('T', ' ').substring(0, 19);
		var cre = (it.created_at || '').replace('T', ' ').substring(0, 19);
		if (!upd && !cre) return '—';
		var html = upd ? ('<b>' + escapeHtml(upd) + '</b>') : '—';
		if (cre && cre !== upd) {
			html += '<div style="font-size:11px;color:#64748b">создан ' + escapeHtml(cre) + '</div>';
		}
		return html;
	}

	function formatQueueError(it) {
		var err = (it.error || '').trim();
		if (!err) return '';
		if (err === 'analysis_failed' || err === '1') {
			return 'анализ в Titlo не сохранился · ' + escapeHtml(it.step_label || it.step || 'анализ');
		}
		return escapeHtml(err);
	}

	function canSelectQueueJob(it) {
		var st = it.status || '';
		return st === 'failed' || st === 'skipped' || st === 'done' || st === 'queued';
	}

	function bindQueueChecks(body) {
		Array.prototype.forEach.call(body.querySelectorAll('.titlo-auto-queue-row'), function (cb) {
			cb.onchange = function () {
				var id = parseInt(cb.value, 10);
				if (cb.checked) queueSelected[id] = true;
				else delete queueSelected[id];
				updateQueueRequeueBtn();
			};
		});
		var all = document.getElementById('titlo-auto-queue-check-all');
		if (all) {
			all.onchange = function () {
				var on = all.checked;
				Array.prototype.forEach.call(body.querySelectorAll('.titlo-auto-queue-row'), function (cb) {
					if (cb.disabled) return;
					cb.checked = on;
					var id = parseInt(cb.value, 10);
					if (on) queueSelected[id] = true;
					else delete queueSelected[id];
				});
				updateQueueRequeueBtn();
			};
		}
	}

	function selectQueueByPred(pred) {
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-queue-row'), function (cb) {
			if (cb.disabled) return;
			var on = !!pred(cb);
			cb.checked = on;
			var id = parseInt(cb.value, 10);
			if (on) queueSelected[id] = true;
			else delete queueSelected[id];
		});
		updateQueueRequeueBtn();
	}

	function loadQueue() {
		return post('auto_queue_status', {
			limit: 40,
			batch_key: lastBatchKey,
			entity_type: entityType,
			tick: hasKey ? 1 : 0
		}).then(function (res) {
			if (!res.ok) return;
			var counts = res.counts || {};
			var cEl = document.getElementById('titlo-auto-queue-counts');
			cEl.textContent = 'В работе: ' + (res.active || 0)
				+ ' · в очереди ' + (counts.queued || 0)
				+ ' · выполняются ' + (counts.running || 0)
				+ ' · готово ' + (counts.done || 0)
				+ ' · текст не писали ' + (counts.skipped || 0)
				+ ' · ошибки ' + (counts.failed || 0);

			var body = document.getElementById('titlo-auto-queue-body');
			var rows = [];
			(res.items || []).forEach(function (it) {
				var selectable = canSelectQueueJob(it);
				var checked = selectable && !!queueSelected[it.id];
				rows.push(
					'<tr>' +
					'<td><input type="checkbox" class="titlo-auto-queue-row" value="' + it.id + '"' +
						' data-status="' + escapeHtml(it.status || '') + '"' +
						' data-run-mode="' + escapeHtml(it.run_mode || '') + '"' +
						(checked ? ' checked' : '') +
						(selectable ? '' : ' disabled') + '></td>' +
					'<td>' + it.id + '</td>' +
					'<td><a href="' + escapeHtml(it.single_url) + '">' + it.entity_id + '</a></td>' +
					'<td>' + escapeHtml(it.phrase) + '</td>' +
					'<td>' + escapeHtml(it.run_mode_label || it.run_mode || '') + '</td>' +
					'<td>' + escapeHtml(it.result_label || it.status_label || it.status) + '</td>' +
					'<td>' + (it.history_id || '—') + '</td>' +
					'<td>' + formatQueueWhen(it) + '</td>' +
					'<td class="titlo-error">' + formatQueueError(it) + '</td>' +
					'</tr>'
				);
			});
			body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="9">Пока пусто</td></tr>';
			bindQueueChecks(body);
			updateQueueRequeueBtn();
			var all = document.getElementById('titlo-auto-queue-check-all');
			if (all) all.checked = false;

			if ((res.active || 0) > 0) {
				schedulePoll();
			}
		});
	}

	function schedulePoll() {
		if (pollTimer) return;
		pollTimer = setTimeout(function () {
			pollTimer = null;
			loadQueue().then(function () {
				loadList();
			});
		}, 8000);
	}

	document.getElementById('titlo-auto-gen-preview').onchange = function () {
		document.getElementById('titlo-auto-preview-wrap').style.display = this.checked ? '' : 'none';
	};

	document.getElementById('titlo-auto-run-mode').onchange = function () {
		syncModeUi();
		loadPrompts();
	};

	document.getElementById('titlo-auto-check-all').onchange = function () {
		var on = this.checked;
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-row'), function (cb) {
			cb.checked = on;
			var id = parseInt(cb.value, 10);
			if (on) selected[id] = true;
			else delete selected[id];
		});
		updateEnqueueBtn();
	};

	document.getElementById('titlo-auto-reload').onclick = function () {
		page = 1;
		loadList();
	};
	document.getElementById('titlo-auto-filter').onchange = function () {
		page = 1;
		loadList();
	};
	document.getElementById('titlo-auto-sort').onchange = function () {
		page = 1;
		loadList();
	};
	document.getElementById('titlo-auto-q').onkeydown = function (e) {
		if (e.key === 'Enter') {
			page = 1;
			loadList();
		}
	};

	document.getElementById('titlo-auto-enqueue').onclick = function () {
		var ids = Object.keys(selected).filter(function (k) { return selected[k]; }).map(function (k) { return parseInt(k, 10); });
		if (!ids.length) return;
		setStatus('titlo-auto-run-status', 'Ставим в очередь…');
		post('auto_enqueue_batch', {
			entity_type: entityType,
			ids: JSON.stringify(ids),
			run_mode: document.getElementById('titlo-auto-run-mode').value,
			prompt_detail_id: document.getElementById('titlo-auto-prompt-detail').value || 0,
			prompt_preview_id: document.getElementById('titlo-auto-prompt-preview').value || 0,
			gen_preview: document.getElementById('titlo-auto-gen-preview').checked ? '1' : '0',
			existing_policy: document.getElementById('titlo-auto-policy').value,
			tlp_missing_limit: document.getElementById('titlo-auto-tlp-missing').value,
			tlp_diff_limit: document.getElementById('titlo-auto-tlp-diff').value
		}).then(function (res) {
			if (!res.ok) {
				setStatus('titlo-auto-run-status', res.error || 'Не удалось поставить', true);
				return;
			}
			lastBatchKey = res.batch_key || '';
			selected = {};
			updateEnqueueBtn();
			var errN = res.errors ? Object.keys(res.errors).length : 0;
			setStatus(
				'titlo-auto-run-status',
				'В очереди: ' + (res.queued || 0) + (errN ? (', ошибок: ' + errN) : '')
			);
			loadList();
			loadQueue();
			schedulePoll();
		});
	};

	document.getElementById('titlo-auto-queue-select-failed').onclick = function () {
		selectQueueByPred(function (cb) {
			return (cb.getAttribute('data-status') || '') === 'failed';
		});
	};
	document.getElementById('titlo-auto-queue-select-no-text').onclick = function () {
		selectQueueByPred(function (cb) {
			var st = cb.getAttribute('data-status') || '';
			var mode = cb.getAttribute('data-run-mode') || '';
			return st === 'skipped' || (st === 'done' && mode === 'analyze_only');
		});
	};

	document.getElementById('titlo-auto-requeue-selected').onclick = function () {
		var ids = Object.keys(queueSelected).filter(function (k) { return queueSelected[k]; }).map(function (k) { return parseInt(k, 10); });
		if (!ids.length) return;
		setStatus('titlo-auto-requeue-status', 'Ставим в очередь…');
		post('auto_requeue_jobs', {
			ids: JSON.stringify(ids),
			entity_type: 'E',
			run_mode: document.getElementById('titlo-auto-run-mode').value,
			existing_policy: document.getElementById('titlo-auto-policy').value,
			prompt_detail_id: document.getElementById('titlo-auto-prompt-detail').value || 0,
			prompt_preview_id: document.getElementById('titlo-auto-prompt-preview').value || 0,
			gen_preview: document.getElementById('titlo-auto-gen-preview').checked ? '1' : '0',
			tlp_missing_limit: document.getElementById('titlo-auto-tlp-missing').value,
			tlp_diff_limit: document.getElementById('titlo-auto-tlp-diff').value
		}).then(function (res) {
			if (!res.ok) {
				setStatus('titlo-auto-requeue-status', res.error || 'Не удалось', true);
				return;
			}
			queueSelected = {};
			updateQueueRequeueBtn();
			setStatus('titlo-auto-requeue-status', 'В очереди: ' + (res.requeued || 0));
			loadList();
			loadQueue();
			schedulePoll();
		});
	};

	syncModeUi();
	loadPrompts().then(loadList).then(loadQueue);
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
