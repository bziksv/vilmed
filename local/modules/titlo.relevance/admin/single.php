<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\CatalogRepository;
use Titlo\Relevance\Config;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');

$APPLICATION->SetTitle('Titlo: проработка товара / категории');

$entityType = strtoupper((string) ($_GET['ENTITY'] ?? $_GET['entity'] ?? 'E'));
if ($entityType !== 'S') {
	$entityType = 'E';
}
$entityId = (int) ($_GET['ID'] ?? $_GET['id'] ?? 0);

$prefill = null;
if ($entityId > 0) {
	$prefill = $entityType === 'S'
		? CatalogRepository::findSection($entityId)
		: CatalogRepository::findElement($entityId);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-box">
	<?php if (!$hasKey): ?>
		<div class="adm-info-message-wrap adm-info-message-red">
			<div class="adm-info-message">
				Сначала укажите API-ключ в
				<a href="/bitrix/admin/settings.php?lang=<?= LANGUAGE_ID ?>&mid=titlo.relevance">настройках модуля</a>.
			</div>
		</div>
	<?php endif; ?>

	<div class="adm-detail-content-item-block">
		<table class="adm-detail-content-table edit-table">
			<tr>
				<td class="adm-detail-content-cell-l" width="30%">Тип</td>
				<td class="adm-detail-content-cell-r">
					<label><input type="radio" name="entity_type" value="E" <?= $entityType === 'E' ? 'checked' : '' ?>> Товар</label>
					&nbsp;
					<label><input type="radio" name="entity_type" value="S" <?= $entityType === 'S' ? 'checked' : '' ?>> Категория</label>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">Поиск (ID / название / код)</td>
				<td class="adm-detail-content-cell-r">
					<input type="text" id="titlo-search-q" class="adm-input" style="width:360px" value="<?= $prefill ? htmlspecialcharsbx($prefill['name']) : '' ?>">
					<input type="button" id="titlo-search-btn" class="adm-btn" value="Найти">
					<div id="titlo-search-results" class="titlo-search-results"></div>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">Выбрано</td>
				<td class="adm-detail-content-cell-r">
					<input type="hidden" id="titlo-entity-id" value="<?= $prefill ? (int) $prefill['id'] : 0 ?>">
					<strong id="titlo-entity-label"><?= $prefill ? htmlspecialcharsbx('#' . $prefill['id'] . ' — ' . $prefill['name']) : '—' ?></strong>
					<a id="titlo-prod-link" href="#" target="_blank" rel="noopener" class="titlo-prod-link" style="display:none;margin-left:10px">на проде ↗</a>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">URL посадочной</td>
				<td class="adm-detail-content-cell-r">
					<input type="text" id="titlo-url" class="adm-input" style="width:100%" value="<?= $prefill ? htmlspecialcharsbx($prefill['url']) : '' ?>">
					<div style="margin-top:4px">
						<a id="titlo-prod-link-url" href="#" target="_blank" rel="noopener" class="titlo-prod-link" style="display:none;font-size:12px">Открыть на проде</a>
					</div>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">Ключевая фраза</td>
				<td class="adm-detail-content-cell-r">
					<input type="text" id="titlo-phrase" class="adm-input" style="width:100%" maxlength="50" value="<?= $prefill ? htmlspecialcharsbx(\Titlo\Relevance\CatalogRepository::preferredPhrase($prefill)) : '' ?>">
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">Поиск / регион / топ
					<span class="titlo-help" tabindex="0" aria-label="Параметры выдачи">?
		<span class="titlo-help__tip">Параметры сбора выдачи. Яндекс и Google — разные ID городов (lr vs geo). Смена ПС подставляет Москву для этой ПС. History_id ниже — прошлый анализ по URL, сам по себе от смены ПС не меняется: новый появится после нового запуска.</span>
					</span>
				</td>
				<td class="adm-detail-content-cell-r">
					<label style="margin-right:12px">ПС
						<select id="titlo-engine" class="adm-input" style="width:120px">
							<option value="yandex" selected>Яндекс</option>
							<option value="google">Google</option>
						</select>
					</label>
					<label style="margin-right:12px">Регион
						<span class="titlo-region-ac" id="titlo-region-ac">
							<input type="hidden" id="titlo-region" value="<?= htmlspecialcharsbx(Config::analysisRegionDefault('yandex')) ?>">
							<input type="text" id="titlo-region-q" class="adm-input" autocomplete="off"
								placeholder="Начните вводить город"
								value="<?= htmlspecialcharsbx(Config::analysisRegionLabel(Config::analysisRegionDefault('yandex'), 'yandex')) ?>">
							<div id="titlo-region-list" class="titlo-region-ac__list"></div>
						</span>
					</label>
					<label>ТОП
						<select id="titlo-top" class="adm-input" style="width:80px">
							<?php foreach (Config::analysisTopOptions() as $topOpt): ?>
								<option value="<?= (int) $topOpt ?>"<?= (int) $topOpt === Config::analysisTopDefault() ? ' selected' : '' ?>>
									<?= (int) $topOpt ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l">Готовый history_id</td>
				<td class="adm-detail-content-cell-r">
					<input type="text" id="titlo-history-id" class="adm-input" style="width:160px" placeholder="например 55371">
					<input type="button" id="titlo-load-history" class="adm-btn" value="Подтянуть фразы">
				</td>
			</tr>
		</table>
		<div style="margin-top:12px">
			<input type="button" id="titlo-start-analysis" class="adm-btn-save" value="Запустить анализ релевантности" <?= $hasKey ? '' : 'disabled' ?>>
			<span id="titlo-analysis-status" class="titlo-status"></span>
		</div>
	</div>

	<div class="adm-detail-content-item-block">
		<h3>Результат анализа / динамика</h3>
		<div id="titlo-scores" class="titlo-scores-box">
			<div class="titlo-status" style="margin:0">Пока нет данных — выберите товар (подтянем прошлые проверки) или запустите новый анализ.</div>
		</div>
		<div id="titlo-hist-wrap" style="display:none">
			<strong style="font-size:13px">Прошлые проверки этой посадочной</strong>
			<table class="titlo-hist-table">
				<thead><tr><th>Дата</th><th>history_id</th><th>Домен</th><th>ПС</th><th>Регион</th><th>ТОП</th><th>Баллы (ваш / рек.)
					<span class="titlo-help" tabindex="0" aria-label="Про рекомендуемый балл">?
						<span class="titlo-help__tip">До рекомендованного балла доходить не всегда нужно. Изучите конкурентов: часть слов часто в меню, шапке, футере и других сквозных блоках — их не обязательно вшивать в текст посадочной.</span>
					</span>
				</th><th>Δ</th><th>Текст, сл.
					<span class="titlo-help" tabindex="0" aria-label="Размер текста">?
						<span class="titlo-help__tip">Сколько слов анализатор посчитал на посадочной (текст + ссылки зоны контента). Рядом — среднее по конкурентам из ТОПа. Δ — изменение к предыдущей проверке этой посадочной.</span>
					</span>
				</th><th>Покрытие</th><th>Позиция</th><th></th></tr></thead>
				<tbody id="titlo-hist-body"></tbody>
			</table>
		</div>
		<div class="titlo-clouds-wrap" id="titlo-clouds-wrap" style="display:none">
			<input type="button" id="titlo-tfidf-clouds-btn" class="adm-btn titlo-clouds-btn" value="Облака TF-IDF посадочной и конкурентов">
			<span class="titlo-help" tabindex="0" aria-label="Про облака TF-IDF">?
				<span class="titlo-help__tip">Те же hybrid TF-IDF, что в кабинете на /show-history: слева средние по ТОПу, справа — ваша посадочная. Текст и ссылки отдельно — чтобы видеть сквозные слова из меню/шапки.</span>
			</span>
			<div class="titlo-clouds-panel" id="titlo-clouds-panel">
				<div class="titlo-clouds-grid">
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">Средние TF-IDF ссылок и текста конкурентов</div>
						<div class="titlo-cloud" id="titlo-cloud-comp-total"></div>
					</div>
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">TF-IDF ссылок и текста посадочной</div>
						<div class="titlo-cloud" id="titlo-cloud-land-total"></div>
					</div>
				</div>
				<div class="titlo-clouds-grid">
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">Средние TF-IDF текста конкурентов</div>
						<div class="titlo-cloud" id="titlo-cloud-comp-text"></div>
					</div>
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">TF-IDF текста посадочной</div>
						<div class="titlo-cloud" id="titlo-cloud-land-text"></div>
					</div>
				</div>
				<div class="titlo-clouds-grid">
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">Средние TF-IDF ссылок конкурентов</div>
						<div class="titlo-cloud" id="titlo-cloud-comp-links"></div>
					</div>
					<div class="titlo-cloud-card">
						<div class="titlo-cloud-card__title">TF-IDF ссылок посадочной</div>
						<div class="titlo-cloud" id="titlo-cloud-land-links"></div>
					</div>
				</div>
			</div>
		</div>
		<h3 style="margin-top:16px">Топ-лист фраз (TLP)
			<span class="titlo-help" tabindex="0" aria-label="Связь с генерацией">?
				<span class="titlo-help__tip">Те же группы unigram и TF-IDF ТОП, что в кабинете (/show-history). Две таблицы: слова, которых нет на посадочной, и слова с разницей к конкурентам. Лимиты режут срез в промпт.</span>
			</span>
		</h3>
		<div class="titlo-tlp-limits" id="titlo-tlp-limits" style="display:none;margin:8px 0 10px;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px">
			<label style="margin-right:16px">Нет на сайте
				<input type="number" id="titlo-tlp-missing-limit" class="adm-input" value="300" min="0" max="500" style="width:70px">
				<span class="titlo-status" id="titlo-tlp-missing-avail"></span>
			</label>
			<label style="margin-right:16px">С разницей
				<input type="number" id="titlo-tlp-diff-limit" class="adm-input" value="5" min="0" max="200" style="width:70px">
				<span class="titlo-status" id="titlo-tlp-diff-avail"></span>
			</label>
			<span class="titlo-status">Сортировка: TF-IDF ТОП ↓</span>
		</div>
		<p class="titlo-kw-hint" id="titlo-kw-hint">В генерацию пока нечего отправлять — сначала анализ или загрузка history_id.</p>
		<div id="titlo-phrases" class="titlo-phrases">
			<div class="titlo-phrases__col">
				<h4>Нет на сайте</h4>
				<div class="titlo-phrases__scroll">
					<table>
						<thead><tr><th>Слово</th><th>TF-IDF ТОП</th><th>У конкурентов</th><th>На сайте</th><th>Ориентир</th></tr></thead>
						<tbody id="titlo-tlp-missing-body"><tr><td colspan="5">Пока пусто</td></tr></tbody>
					</table>
				</div>
			</div>
			<div class="titlo-phrases__col">
				<h4>С разницей (добавить)</h4>
				<div class="titlo-phrases__scroll">
					<table>
						<thead><tr><th>Слово</th><th>TF-IDF ТОП</th><th>У конкурентов</th><th>На сайте</th><th>Ориентир</th></tr></thead>
						<tbody id="titlo-tlp-diff-body"><tr><td colspan="5">Пока пусто</td></tr></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="adm-detail-content-item-block">
		<h3>Генерация текстов
			<span class="titlo-help" tabindex="0" aria-label="Как связаны фразы и промпт">?
				<span class="titlo-help__tip">1) Промпт из селекта.<br>2) К нему дописывается выбранный срез TLP: первые ~40 слов — обязательно, остальные желательно.<br>3) Плюс HTML страницы по URL.</span>
			</span>
		</h3>
		<p class="titlo-status" style="margin:0 0 10px">
			Промпт выбираете здесь. Слова из TLP выше (по лимитам) при генерации дописываются в конец: топ обязателен, остальное — по возможности.
			После первого прохода, если балл ещё низкий, выберите промпт «Повторная доработка» или «Повторная доработка: со стилями (Vilmed)» — TLP берётся из текущего history_id.
		</p>
		<div class="titlo-gen-row" data-type="preview">
			<label>Анонс — промпт
				<select class="titlo-prompt-select adm-input" data-type="preview" style="min-width:280px"></select>
			</label>
			<span class="titlo-prompt-meta titlo-status"></span>
			<input type="button" class="adm-btn titlo-gen-btn" data-type="preview" value="Сгенерировать анонс" <?= $hasKey ? '' : 'disabled' ?>>
		</div>
		<div class="titlo-gen-row" data-type="detail" style="margin-top:8px">
			<label>Детальное — промпт
				<select class="titlo-prompt-select adm-input" data-type="detail" style="min-width:280px"></select>
			</label>
			<span class="titlo-prompt-meta titlo-status"></span>
			<input type="button" class="adm-btn titlo-gen-btn" data-type="detail" value="Сгенерировать детальное" <?= $hasKey ? '' : 'disabled' ?>>
		</div>
		<div class="titlo-gen-row" data-type="category" style="margin-top:8px">
			<label>Категория — промпт
				<select class="titlo-prompt-select adm-input" data-type="category" style="min-width:280px"></select>
			</label>
			<span class="titlo-prompt-meta titlo-status"></span>
			<input type="button" class="adm-btn titlo-gen-btn" data-type="category" value="Сгенерировать категорию" <?= $hasKey ? '' : 'disabled' ?>>
		</div>
		<span id="titlo-gen-status" class="titlo-status" style="display:block;margin-top:8px"></span>
		<div class="titlo-preview" style="margin-top:12px">
			<label>Анонс</label>
			<textarea id="titlo-text-preview"></textarea>
			<label>Детальное</label>
			<textarea id="titlo-text-detail"></textarea>
			<label>Категория</label>
			<textarea id="titlo-text-category"></textarea>
		</div>
		<div style="margin-top:12px">
			<input type="button" id="titlo-save" class="adm-btn-save" value="Сохранить в Bitrix с подтверждением">
			<span id="titlo-save-status" class="titlo-status"></span>
		</div>
	</div>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var historyId = null;
	var keywords = [];
	var tlpMissing = [];
	var tlpDiff = [];
	var tlpMissingTotal = 0;
	var tlpDiffTotal = 0;
	var regionsByEngine = {
		yandex: <?= json_encode(Config::analysisYandexRegionsList(), JSON_UNESCAPED_UNICODE) ?>,
		google: <?= json_encode(Config::analysisGoogleRegionsList(), JSON_UNESCAPED_UNICODE) ?>
	};
	var popularByEngine = {
		yandex: <?= json_encode(array_keys(Config::analysisRegions())) ?>,
		google: <?= json_encode(array_keys(Config::analysisGoogleRegions())) ?>
	};
	var defaultRegionByEngine = {
		yandex: <?= json_encode(Config::analysisRegionDefault('yandex')) ?>,
		google: <?= json_encode(Config::analysisRegionDefault('google')) ?>
	};
	var savedRegionByEngine = {yandex: '', google: ''};
	var prodOrigin = <?= json_encode(rtrim(Config::get('site_url', ''), '/'), JSON_UNESCAPED_UNICODE) ?>;

	function prodUrlFromLanding(url) {
		url = String(url || '').trim();
		if (!prodOrigin || !url) return '';
		try {
			var u = new URL(url, window.location.origin);
			return prodOrigin + (u.pathname || '/') + (u.search || '') + (u.hash || '');
		} catch (e) {
			return prodOrigin + '/' + url.replace(/^\//, '');
		}
	}

	function updateProdLinks() {
		var href = prodUrlFromLanding((document.getElementById('titlo-url') || {}).value || '');
		['titlo-prod-link', 'titlo-prod-link-url'].forEach(function (id) {
			var a = document.getElementById(id);
			if (!a) return;
			if (!href) {
				a.style.display = 'none';
				a.removeAttribute('href');
				return;
			}
			a.href = href;
			a.style.display = '';
		});
	}

	function currentEngine() {
		var el = document.getElementById('titlo-engine');
		return (el && el.value === 'google') ? 'google' : 'yandex';
	}

	function regionList() {
		return regionsByEngine[currentEngine()] || [];
	}

	function regionLabelById(id, engine) {
		id = String(id || '');
		engine = engine || currentEngine();
		var list = regionsByEngine[engine] || [];
		for (var i = 0; i < list.length; i++) {
			if (String(list[i].id) === id) {
				return (list[i].name || '') + ' [' + id + ']';
			}
		}
		return id;
	}

	function filterRegions(q) {
		q = String(q || '').trim().toLowerCase();
		var engine = currentEngine();
		var skip = engine === 'yandex' ? {'225': 1} : {};
		var out = [];
		var list = regionList();
		if (!q) {
			(popularByEngine[engine] || []).forEach(function (id) {
				id = String(id);
				if (skip[id]) return;
				out.push({id: id, text: regionLabelById(id, engine)});
			});
			return out;
		}
		for (var i = 0; i < list.length; i++) {
			var id = String(list[i].id || '');
			if (skip[id]) continue;
			var name = String(list[i].name || '');
			var hay = (name + ' ' + id).toLowerCase();
			if (hay.indexOf(q) === -1) continue;
			out.push({id: id, text: name + ' [' + id + ']'});
			if (out.length >= 25) break;
		}
		return out;
	}

	function setRegion(id, text) {
		var hid = document.getElementById('titlo-region');
		var inp = document.getElementById('titlo-region-q');
		var engine = currentEngine();
		if (hid) hid.value = String(id || '');
		if (inp) inp.value = text || regionLabelById(id, engine);
		savedRegionByEngine[engine] = String(id || '');
		closeRegionList();
	}

	function applyEngineRegion() {
		var engine = currentEngine();
		var id = savedRegionByEngine[engine] || defaultRegionByEngine[engine];
		setRegion(id, regionLabelById(id, engine));
	}

	function closeRegionList() {
		var wrap = document.getElementById('titlo-region-ac');
		if (wrap) wrap.classList.remove('is-open');
	}

	function renderRegionList(items) {
		var box = document.getElementById('titlo-region-list');
		var wrap = document.getElementById('titlo-region-ac');
		if (!box || !wrap) return;
		if (!items.length) {
			box.innerHTML = '<div class="empty">Ничего не найдено — введите город, например «Казань»</div>';
		} else {
			box.innerHTML = items.map(function (it) {
				return '<div data-id="' + it.id + '">' + it.text.replace(/</g, '') + '</div>';
			}).join('');
		}
		wrap.classList.add('is-open');
		Array.prototype.forEach.call(box.querySelectorAll('div[data-id]'), function (el) {
			el.onclick = function () {
				setRegion(el.getAttribute('data-id'), el.textContent);
			};
		});
	}

	(function initRegionSearch() {
		var inp = document.getElementById('titlo-region-q');
		var eng = document.getElementById('titlo-engine');
		if (!inp) return;
		inp.addEventListener('focus', function () {
			var curId = document.getElementById('titlo-region').value;
			var curLabel = regionLabelById(curId);
			renderRegionList(filterRegions(inp.value === curLabel ? '' : inp.value));
		});
		inp.addEventListener('input', function () {
			renderRegionList(filterRegions(inp.value));
		});
		inp.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape') closeRegionList();
			if (ev.key === 'Enter') {
				ev.preventDefault();
				var first = document.querySelector('#titlo-region-list div[data-id]');
				if (first) setRegion(first.getAttribute('data-id'), first.textContent);
			}
		});
		if (eng) {
			eng.addEventListener('change', function () {
				applyEngineRegion();
			});
		}
		document.addEventListener('click', function (ev) {
			var wrap = document.getElementById('titlo-region-ac');
			if (wrap && !wrap.contains(ev.target)) closeRegionList();
		});
	})();

	function entityType() {
		var el = document.querySelector('input[name="entity_type"]:checked');
		return el ? el.value : 'E';
	}

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

	function setStatus(id, text, isError) {
		var el = document.getElementById(id);
		el.textContent = text || '';
		el.className = 'titlo-status' + (isError ? ' titlo-error' : (text ? ' titlo-ok' : ''));
	}

	function readTlpLimits() {
		var missLim = parseInt((document.getElementById('titlo-tlp-missing-limit') || {}).value, 10);
		var diffLim = parseInt((document.getElementById('titlo-tlp-diff-limit') || {}).value, 10);
		if (isNaN(missLim) || missLim < 0) missLim = 0;
		if (isNaN(diffLim) || diffLim < 0) diffLim = 0;
		if (missLim > 500) missLim = 500;
		if (diffLim > 200) diffLim = 200;
		return {missing: missLim, diff: diffLim};
	}

	function rebuildKeywordsFromTlp() {
		var lim = readTlpLimits();
		keywords = [];
		tlpMissing.slice(0, lim.missing).forEach(function (item) {
			keywords.push({word: item.word, count: item.suggested_count || 1, bucket: 'missing'});
		});
		tlpDiff.slice(0, lim.diff).forEach(function (item) {
			keywords.push({word: item.word, count: item.suggested_count || 1, bucket: 'diff'});
		});
		updateKwHint();
		renderTlpTable();
	}

	function updateKwHint() {
		var el = document.getElementById('titlo-kw-hint');
		if (!el) return;
		var lim = readTlpLimits();
		var takeM = Math.min(lim.missing, tlpMissing.length);
		var takeD = Math.min(lim.diff, tlpDiff.length);
		var n = keywords.length;
		if (n < 1) {
			el.innerHTML = 'В генерацию пока <b>нечего</b> отправлять — сначала анализ/history_id или увеличьте лимиты TLP.';
			return;
		}
		el.innerHTML = 'В генерацию уйдёт <b>' + n + '</b> слов TLP: '
			+ '<b>' + takeM + '</b> нет на сайте + <b>' + takeD + '</b> с разницей '
			+ '(сортировка TF-IDF ТОП). Первые ~40 — обязательно, остальные желательно.';
	}

	function renderTlpTable() {
		var lim = readTlpLimits();
		function fillBody(bodyId, list, take) {
			var body = document.getElementById(bodyId);
			if (!body) return;
			var rows = [];
			list.slice(0, take).forEach(function (item) {
				rows.push(
					'<tr><td>' + escapeHtml(item.word) + '</td>' +
					'<td>' + (item.tfidf_top != null ? escapeHtml(Number(item.tfidf_top).toFixed(4)) : '—') + '</td>' +
					'<td>' + (item.avg_competitors != null ? escapeHtml(String(item.avg_competitors)) : '—') + '</td>' +
					'<td>' + (item.on_landing != null ? escapeHtml(String(item.on_landing)) : '—') + '</td>' +
					'<td>' + escapeHtml(String(item.suggested_count || 1)) + '</td></tr>'
				);
			});
			body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="5">По выбранному лимиту пусто</td></tr>';
		}
		fillBody('titlo-tlp-missing-body', tlpMissing, lim.missing);
		fillBody('titlo-tlp-diff-body', tlpDiff, lim.diff);
	}

	function renderPhrases(payload) {
		tlpMissing = payload.missing || [];
		tlpDiff = payload.diff || [];
		tlpMissingTotal = parseInt(payload.missing_total, 10) || tlpMissing.length;
		tlpDiffTotal = parseInt(payload.diff_total, 10) || tlpDiff.length;

		var defaults = payload.defaults || {};
		var missInput = document.getElementById('titlo-tlp-missing-limit');
		var diffInput = document.getElementById('titlo-tlp-diff-limit');
		if (missInput && defaults.missing_limit != null && !missInput.dataset.userTouched) {
			missInput.value = String(defaults.missing_limit);
		}
		if (diffInput && defaults.diff_limit != null && !diffInput.dataset.userTouched) {
			diffInput.value = String(defaults.diff_limit);
		}
		var availM = document.getElementById('titlo-tlp-missing-avail');
		var availD = document.getElementById('titlo-tlp-diff-avail');
		if (availM) availM.textContent = 'из ' + tlpMissingTotal;
		if (availD) availD.textContent = 'из ' + tlpDiffTotal;
		var box = document.getElementById('titlo-tlp-limits');
		if (box) box.style.display = '';

		rebuildKeywordsFromTlp();
	}

	var cloudsCache = null;
	var cloudsHistoryId = 0;
	var cloudsLoading = false;
	var CLOUD_MAX_WORDS = 100;
	var CLOUD_LEADER_COUNT = 20;
	var CLOUD_SIZE_MIN = 13;
	var CLOUD_SIZE_MAX = 49;
	var CLOUD_VISUAL_FLOOR = 0.2;

	function ensureCloudTip() {
		var tip = document.getElementById('titlo-cloud-tip');
		if (tip) return tip;
		tip = document.createElement('div');
		tip.id = 'titlo-cloud-tip';
		tip.className = 'titlo-cloud-tip';
		document.body.appendChild(tip);
		return tip;
	}

	function formatCloudWeight(w) {
		var n = parseFloat(w);
		if (!isFinite(n)) return String(w);
		if (Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
		return n.toFixed(4).replace(/\.?0+$/, '');
	}

	function normalizeCloudWords(words) {
		if (!words || !words.length) return [];
		return words.slice().map(function (w) {
			return {
				text: String(w.text || ''),
				weight: parseFloat(w.weight) || 0
			};
		}).filter(function (w) {
			return w.text && w.weight > 0;
		}).sort(function (a, b) {
			return b.weight - a.weight;
		}).slice(0, CLOUD_MAX_WORDS);
	}

	function cloudVisualRatio(rank, word, words) {
		var total = words.length;
		if (!total) return 1;
		var weight = Math.max(0, word.weight || 0);
		var maxW = Math.max(0, words[0].weight || 0);
		if (maxW <= 0) return 0.5;
		var share = Math.min(1, weight / maxW);
		var weightVisual = Math.pow(share, 0.72);
		var leaderCount = Math.min(CLOUD_LEADER_COUNT, total);
		var ratio;
		if (rank < leaderCount) {
			var tierBoost = rank < 3 ? 1.06 : (rank < 10 ? 1.03 : 1.01);
			ratio = Math.min(1, weightVisual * tierBoost);
		} else {
			ratio = Math.max(0.12, Math.min(0.45, weightVisual * 0.5));
		}
		var clamped = Math.max(0, Math.min(1, ratio));
		if (clamped >= 1) return 1;
		return CLOUD_VISUAL_FLOOR + (1 - CLOUD_VISUAL_FLOOR) * Math.pow(clamped, 0.85);
	}

	function cloudFontSizePx(visualRatio, text) {
		var len = String(text).length;
		var lenPenalty = Math.min(8, Math.max(0, len - 6) * 0.35);
		var spread = CLOUD_SIZE_MAX - CLOUD_SIZE_MIN;
		return Math.round(CLOUD_SIZE_MIN + visualRatio * spread - lenPenalty);
	}

	function cloudWeightClass(ratio) {
		var bucket = Math.max(1, Math.min(10, Math.round(ratio * 9) + 1));
		return 'titlo-spiral-cloud__word--w' + bucket;
	}

	function boxesOverlap(a, b, pad) {
		pad = pad || 5;
		return !(a.right + pad <= b.left || a.left >= b.right + pad || a.bottom + pad <= b.top || a.top >= b.bottom + pad);
	}

	function paintSpiralCloud(host, rawWords, done) {
		var words = normalizeCloudWords(rawWords);
		host.innerHTML = '';
		host.style.height = (words.length >= 80 ? 400 : 360) + 'px';

		if (!words.length) {
			host.innerHTML = '<span class="titlo-cloud__empty">Нет данных</span>';
			if (typeof done === 'function') done();
			return;
		}

		var hostW = Math.max(240, Math.floor(host.clientWidth || host.offsetWidth || 400));
		var hostH = Math.max(280, Math.floor(parseInt(host.style.height, 10) || 360));
		var placed = [];
		var cx = hostW / 2;
		var cy = hostH / 2;
		var edgePad = 6;
		var collisionPad = 1;
		var maxAttempts = 950;
		var wrap = document.createElement('div');
		wrap.className = 'titlo-spiral-cloud';
		wrap.style.width = hostW + 'px';
		wrap.style.height = hostH + 'px';
		host.appendChild(wrap);

		var index = 0;
		var chunkSize = 20;

		function placeWordElement(el, w, h, wordIndex) {
			var angle = 0;
			var radius = 0;
			var step = 0.36;
			var i, x, y, box;

			if (wordIndex === 0) {
				x = cx;
				y = cy;
				box = {left: x - w / 2, top: y - h / 2, right: x + w / 2, bottom: y + h / 2};
				el.style.left = x + 'px';
				el.style.top = y + 'px';
				el.classList.add('titlo-spiral-cloud__word--center');
				placed.push(box);
				return true;
			}

			radius = Math.max(w, h) * 0.42;
			for (i = 0; i < maxAttempts; i++) {
				x = cx + radius * Math.cos(angle);
				y = cy + radius * Math.sin(angle);
				box = {left: x - w / 2, top: y - h / 2, right: x + w / 2, bottom: y + h / 2};
				if (box.left < edgePad || box.top < edgePad || box.right > hostW - edgePad || box.bottom > hostH - edgePad) {
					angle += step;
					radius += 0.5;
					continue;
				}
				var collision = false;
				for (var j = 0; j < placed.length; j++) {
					if (boxesOverlap(box, placed[j], collisionPad)) {
						collision = true;
						break;
					}
				}
				if (!collision) {
					el.style.left = x + 'px';
					el.style.top = y + 'px';
					placed.push(box);
					return true;
				}
				angle += step;
				radius += 0.5;
			}
			return false;
		}

		function paintChunk() {
			var end = Math.min(index + chunkSize, words.length);
			for (; index < end; index++) {
				var word = words[index];
				var visualRatio = cloudVisualRatio(index, word, words);
				var sizePx = cloudFontSizePx(visualRatio, word.text);
				var el = document.createElement('span');
				el.className = 'titlo-spiral-cloud__word ' + cloudWeightClass(visualRatio);
				el.textContent = word.text;
				el.setAttribute('data-tip', word.text + ' — ' + formatCloudWeight(word.weight));
				wrap.appendChild(el);

				var placedWord = false;
				for (var attempt = 0; attempt < 6; attempt++) {
					var trySize = Math.max(11, Math.round(sizePx * Math.pow(0.9, attempt)));
					el.style.fontSize = trySize + 'px';
					if (placeWordElement(el, el.offsetWidth, el.offsetHeight, index)) {
						placedWord = true;
						break;
					}
				}
				if (!placedWord) {
					wrap.removeChild(el);
				}
			}
			if (index < words.length) {
				window.requestAnimationFrame(paintChunk);
				return;
			}
			if (typeof done === 'function') done();
		}

		paintChunk();
	}

	function renderCloudsData(data, done) {
		var comp = data.competitors || {};
		var land = data.landing || {};
		var jobs = [
			['titlo-cloud-comp-total', comp.total || []],
			['titlo-cloud-land-total', land.total || []],
			['titlo-cloud-comp-text', comp.text || []],
			['titlo-cloud-land-text', land.text || []],
			['titlo-cloud-comp-links', comp.links || []],
			['titlo-cloud-land-links', land.links || []]
		];
		var i = 0;
		function next() {
			if (i >= jobs.length) {
				if (typeof done === 'function') done();
				return;
			}
			var job = jobs[i++];
			var el = document.getElementById(job[0]);
			if (!el) {
				next();
				return;
			}
			paintSpiralCloud(el, job[1], next);
		}
		// После показа панели нужен layout, иначе ширина = 0
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(next);
		});
	}

	function resetCloudsUi() {
		cloudsCache = null;
		cloudsHistoryId = 0;
		var wrap = document.getElementById('titlo-clouds-wrap');
		var panel = document.getElementById('titlo-clouds-panel');
		var btn = document.getElementById('titlo-tfidf-clouds-btn');
		if (wrap) wrap.style.display = 'none';
		if (panel) panel.classList.remove('is-open');
		if (btn) {
			btn.disabled = false;
			btn.value = 'Облака TF-IDF посадочной и конкурентов';
		}
		['titlo-cloud-comp-total','titlo-cloud-land-total','titlo-cloud-comp-text','titlo-cloud-land-text','titlo-cloud-comp-links','titlo-cloud-land-links'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.innerHTML = '';
		});
	}

	function showCloudsWrap(hid) {
		var wrap = document.getElementById('titlo-clouds-wrap');
		if (!wrap) return;
		if (!hid) {
			resetCloudsUi();
			return;
		}
		wrap.style.display = '';
		if (cloudsHistoryId !== hid) {
			cloudsCache = null;
			cloudsHistoryId = hid;
			var panel = document.getElementById('titlo-clouds-panel');
			if (panel) panel.classList.remove('is-open');
			var btn = document.getElementById('titlo-tfidf-clouds-btn');
			if (btn) btn.value = 'Облака TF-IDF посадочной и конкурентов';
		}
	}

	function loadAndShowClouds() {
		var hid = parseInt(historyId, 10) || 0;
		if (!hid) {
			setStatus('titlo-analysis-status', 'Сначала выберите проверку (history_id)', true);
			return Promise.resolve();
		}
		var panel = document.getElementById('titlo-clouds-panel');
		var btn = document.getElementById('titlo-tfidf-clouds-btn');
		if (panel && panel.classList.contains('is-open') && cloudsCache && cloudsHistoryId === hid) {
			panel.classList.remove('is-open');
			if (btn) btn.value = 'Облака TF-IDF посадочной и конкурентов';
			return Promise.resolve();
		}
		function openWithData(data) {
			if (panel) panel.classList.add('is-open');
			if (btn) {
				btn.disabled = true;
				btn.value = 'Рисую облака…';
			}
			renderCloudsData(data, function () {
				if (btn) {
					btn.disabled = false;
					btn.value = 'Скрыть облака TF-IDF';
				}
			});
		}
		if (cloudsCache && cloudsHistoryId === hid) {
			openWithData(cloudsCache);
			return Promise.resolve();
		}
		if (cloudsLoading) return Promise.resolve();
		cloudsLoading = true;
		if (btn) {
			btn.disabled = true;
			btn.value = 'Загрузка облаков…';
		}
		return post('tfidf_clouds', {history_id: hid, limit: 100}).then(function (res) {
			cloudsLoading = false;
			if (!res.ok) {
				if (btn) {
					btn.disabled = false;
					btn.value = 'Облака TF-IDF посадочной и конкурентов';
				}
				setStatus('titlo-analysis-status', res.error || 'Не удалось загрузить облака', true);
				return;
			}
			var comp = res.competitors || {};
			var land = res.landing || {};
			var hasWords = [comp.total, comp.text, comp.links, land.total, land.text, land.links].some(function (arr) {
				return Array.isArray(arr) && arr.length > 0;
			});
			if (!hasWords) {
				if (btn) {
					btn.disabled = false;
					btn.value = 'Облака TF-IDF посадочной и конкурентов';
				}
				setStatus(
					'titlo-analysis-status',
					'В проверке #' + hid + ' нет данных TF-IDF (облака пустые). Перезапустите анализ или откройте другую проверку.',
					true
				);
				return;
			}
			cloudsCache = res;
			cloudsHistoryId = hid;
			openWithData(res);
		}).catch(function (err) {
			cloudsLoading = false;
			if (btn) {
				btn.disabled = false;
				btn.value = 'Облака TF-IDF посадочной и конкурентов';
			}
			setStatus('titlo-analysis-status', (err && err.message) ? err.message : 'Ошибка облаков', true);
		});
	}

	function fmtDelta(d) {
		if (d == null || d === '') return '<span class="delta-same">—</span>';
		var n = parseFloat(d);
		if (isNaN(n)) return '<span class="delta-same">—</span>';
		if (n > 0) return '<span class="delta-up">+' + n + '</span>';
		if (n < 0) return '<span class="delta-down">' + n + '</span>';
		return '<span class="delta-same">0</span>';
	}

	function fmtDate(s) {
		if (!s) return '—';
		return String(s).replace('T', ' ').replace(/\.\d+Z?$/, '').substring(0, 16);
	}

	var POINTS_IDEAL_TIP =
		'До рекомендованного балла доходить не всегда нужно. Изучите конкурентов: часть слов часто в меню, шапке, футере и других сквозных блоках — их не обязательно вшивать в текст посадочной.';

	function pointsIdealHelpHtml() {
		return '<span class="titlo-help" tabindex="0" aria-label="Про рекомендуемый балл">?' +
			'<span class="titlo-help__tip">' + POINTS_IDEAL_TIP + '</span>' +
			'</span>';
	}

	function fmtPointsPair(h) {
		if (!h || h.points == null) return '—';
		var yours = h.points;
		var ideal = h.points_ideal;
		if (ideal == null || ideal === '') return String(yours);
		return '<b>' + yours + '</b> / ' + ideal;
	}

	function engineLabel(engine) {
		return engine === 'google' ? 'Google' : 'Яндекс';
	}

	function fmtHistoryParams(it) {
		var engine = it.engine || 'yandex';
		var regionId = it.region != null ? String(it.region) : '';
		var regionText = regionId ? regionLabelById(regionId, engine) : '—';
		return {
			engine: engineLabel(engine),
			region: regionText || regionId || '—',
			top: (it.top != null && it.top !== '') ? String(it.top) : '—'
		};
	}

	function fmtTextWords(h) {
		if (!h || h.text_words == null || h.text_words === '') return '—';
		var s = String(h.text_words);
		if (h.text_words_avg != null && h.text_words_avg !== '') {
			s += ' / ср. ' + h.text_words_avg;
		}
		return s;
	}

	function fmtDeltaText(delta) {
		if (delta == null || delta === '' || isNaN(delta)) return '';
		var n = parseInt(delta, 10);
		if (n === 0) return ' <span class="delta-same">0</span>';
		if (n > 0) return ' <span class="delta-up">+' + n + '</span>';
		return ' <span class="delta-down">' + n + '</span>';
	}

	function recordWorkScore(h, role) {
		var id = parseInt(document.getElementById('titlo-entity-id').value, 10) || 0;
		if (!id || !h || !h.history_id) return Promise.resolve();
		var label = (document.getElementById('titlo-entity-label') || {}).textContent || '';
		var nameFromLabel = label.replace(/^#\d+\s*[—\-]\s*/, '').trim();
		return post('work_record_score', {
			entity_type: entityType(),
			entity_id: id,
			name: nameFromLabel || (document.getElementById('titlo-search-q') || {}).value || '',
			url: (document.getElementById('titlo-url') || {}).value || '',
			phrase: (document.getElementById('titlo-phrase') || {}).value || '',
			history_id: h.history_id,
			points: h.points,
			points_ideal: h.points_ideal,
			coverage: h.coverage,
			density: h.density,
			position: h.position,
			engine: h.engine || '',
			region: h.region || '',
			top: h.top || '',
			checked_at: h.last_check || h.created_at || '',
			role: role || 'auto'
		}).then(function (res) {
			if (res && res.ok && res.role) {
				var hint = res.role === 'after'
					? 'История: зафиксировано «после»'
					: 'История: зафиксировано «до»';
				setStatus('titlo-analysis-status', hint);
			}
			return res;
		}).catch(function () { return null; });
	}

	function renderScores(h, delta) {
		var el = document.getElementById('titlo-scores');
		if (!h) {
			el.innerHTML = '<div class="titlo-status" style="margin:0">Пока нет данных — выберите товар или запустите анализ.</div>';
			return;
		}
		var ptsHtml = fmtPointsPair(h);
		var hasIdeal = h.points_ideal != null && h.points_ideal !== '';
		var label = hasIdeal
			? 'ваш / рекомендуемый' + pointsIdealHelpHtml()
			: 'баллов';
		var params = fmtHistoryParams(h);
		var textLine = '';
		if (h.text_words != null && h.text_words !== '') {
			textLine = ' · текст: <b>' + escapeHtml(String(h.text_words)) + '</b> сл.';
			if (h.text_words_avg != null && h.text_words_avg !== '') {
				textLine += ' (ср. конкуренты <b>' + escapeHtml(String(h.text_words_avg)) + '</b>)';
			}
			textLine += fmtDeltaText(h.delta_text_words);
		}
		el.innerHTML =
			'<div class="big">' + ptsHtml + ' <span style="font-size:14px;font-weight:500;color:#64748b">' + label + '</span> ' + fmtDelta(delta) + '</div>' +
			'<div class="titlo-scores-meta">' +
			'history_id=<b>' + (h.history_id || '—') + '</b>' +
			' · <b>' + escapeHtml(params.engine) + '</b>' +
			' · <b>' + escapeHtml(params.region) + '</b>' +
			' · ТОП-<b>' + escapeHtml(params.top) + '</b>' +
			' · покрытие: <b>' + (h.coverage != null ? h.coverage : '—') + '</b>' +
			' · плотность: <b>' + (h.density != null ? h.density : '—') + '</b>' +
			' · позиция: <b>' + (h.position != null ? h.position : '—') + '</b>' +
			textLine +
			(h.last_check || h.created_at ? ' · ' + fmtDate(h.last_check || h.created_at) : '') +
			'</div>';
	}

	function renderHistoryList(items, currentHid) {
		var wrap = document.getElementById('titlo-hist-wrap');
		var body = document.getElementById('titlo-hist-body');
		if (!items || !items.length) {
			wrap.style.display = 'none';
			body.innerHTML = '';
			return;
		}
		wrap.style.display = 'block';
		body.innerHTML = items.map(function (it) {
			var cur = currentHid && parseInt(it.history_id, 10) === parseInt(currentHid, 10);
			var p = fmtHistoryParams(it);
			var host = '—';
			try { if (it.url) host = new URL(it.url).host; } catch (e) {}
			return '<tr class="' + (cur ? 'is-current' : '') + '">' +
				'<td>' + escapeHtml(fmtDate(it.last_check || it.created_at)) + '</td>' +
				'<td>' + it.history_id + '</td>' +
				'<td title="' + escapeHtml(it.url || '') + '">' + escapeHtml(host) + '</td>' +
				'<td>' + escapeHtml(p.engine) + '</td>' +
				'<td>' + escapeHtml(p.region) + '</td>' +
				'<td>' + escapeHtml(p.top) + '</td>' +
				'<td>' + fmtPointsPair(it) + '</td>' +
				'<td>' + fmtDelta(it.delta_points) + '</td>' +
				'<td>' + escapeHtml(fmtTextWords(it)) + fmtDeltaText(it.delta_text_words) + '</td>' +
				'<td>' + (it.coverage != null ? it.coverage : '—') + '</td>' +
				'<td>' + (it.position != null ? it.position : '—') + '</td>' +
				'<td><button type="button" class="linkish" data-hid="' + it.history_id + '" data-delta="' + (it.delta_points != null ? it.delta_points : '') + '" data-delta-text="' + (it.delta_text_words != null ? it.delta_text_words : '') + '">открыть</button></td>' +
				'</tr>';
		}).join('');
		Array.prototype.forEach.call(body.querySelectorAll('button[data-hid]'), function (btn) {
			btn.onclick = function () {
				var hid = btn.getAttribute('data-hid');
				var d = btn.getAttribute('data-delta');
				var dt = btn.getAttribute('data-delta-text');
				loadHistoryBundle(
					hid,
					d === '' || d == null ? null : parseFloat(d),
					dt === '' || dt == null ? null : parseInt(dt, 10)
				);
			};
		});
	}

	function loadLandingStats() {
		var url = document.getElementById('titlo-url').value.trim();
		var phrase = document.getElementById('titlo-phrase').value.trim();
		if (!url && !phrase) {
			renderScores(null);
			renderHistoryList([]);
			resetCloudsUi();
			return Promise.resolve();
		}
		var started = Date.now();
		document.getElementById('titlo-scores').innerHTML =
			'<div class="titlo-status" style="margin:0">Загружаю прошлые проверки по URL… <span id="titlo-hist-wait">0</span> с</div>';
		var tick = setInterval(function () {
			var el = document.getElementById('titlo-hist-wait');
			if (el) el.textContent = String(Math.floor((Date.now() - started) / 1000));
		}, 250);

		// Только URL — быстрее; фраза фильтруется на стороне API после выборки
		return post('list_histories', {url: url, phrase: '', limit: 10}).then(function (res) {
			clearInterval(tick);
			if (!res.ok) {
				document.getElementById('titlo-scores').innerHTML =
					'<div class="titlo-status titlo-error" style="margin:0">' + escapeHtml(res.error || 'Не удалось загрузить историю') + '</div>';
				renderHistoryList([]);
				resetCloudsUi();
				return;
			}
			var items = res.items || [];
			var latest = res.latest || items[0] || null;
			renderHistoryList(items, latest && latest.history_id);
			if (latest && latest.history_id) {
				renderScores(latest, latest.delta_points);
				historyId = parseInt(latest.history_id, 10) || 0;
				document.getElementById('titlo-history-id').value = historyId;
				showCloudsWrap(historyId);
				var hostHint = '';
				try {
					hostHint = latest.url ? (' · ' + new URL(latest.url).host) : '';
				} catch (e) {}
				setStatus(
					'titlo-analysis-status',
					'Найдено проверок: ' + (res.count || items.length) +
					', последняя #' + historyId + hostHint +
					' (это прошлые проверки, не новый анализ)'
				);
				// Не пишем «до» из автоподгрузки чужой/старой истории — только после своего анализа / явного «открыть»
				return post('missing_phrases', {history_id: historyId, mode: 'tlp'}).then(function (p) {
					if (p.ok) renderPhrases(p);
					else setStatus('titlo-analysis-status', p.error || 'Не удалось загрузить TLP', true);
				}).catch(function (err) {
					setStatus('titlo-analysis-status', (err && err.message) ? err.message : 'Ошибка загрузки TLP', true);
				});
			}

			historyId = null;
			document.getElementById('titlo-history-id').value = '';
			renderScores(null);
			resetCloudsUi();
			document.getElementById('titlo-scores').innerHTML =
				'<div class="titlo-status" style="margin:0">По этой посадочной ещё не было проверок на вашем домене — нажмите «Запустить анализ релевантности».</div>';
		}).catch(function (err) {
			clearInterval(tick);
			resetCloudsUi();
			document.getElementById('titlo-scores').innerHTML =
				'<div class="titlo-status titlo-error" style="margin:0">Не ответил кабинет за отведённое время. ' +
				escapeHtml((err && err.message) ? err.message : 'Попробуйте ещё раз или укажите history_id.') + '</div>';
		});
	}

	function loadHistoryBundle(hid, delta, deltaText) {
		historyId = parseInt(hid, 10) || 0;
		if (!historyId) return Promise.resolve();
		document.getElementById('titlo-history-id').value = historyId;
		showCloudsWrap(historyId);
		return post('get_history', {history_id: historyId}).then(function (h) {
			if (h.ok) {
				if (deltaText != null && !isNaN(deltaText)) {
					h.delta_text_words = deltaText;
				}
				renderScores(h, delta != null ? delta : h.delta_points);
				Array.prototype.forEach.call(document.querySelectorAll('#titlo-hist-body tr'), function (tr) {
					var cell = tr.cells[1];
					tr.className = (cell && parseInt(cell.textContent, 10) === historyId) ? 'is-current' : '';
				});
				// peek: не пишем WorkHistory baseline при простом открытии прошлой проверки
			} else {
				renderScores(null);
			}
			return post('missing_phrases', {history_id: historyId, mode: 'tlp'}).then(function (p) {
				if (p.ok) renderPhrases(p);
				else setStatus('titlo-analysis-status', p.error || 'Ошибка фраз', true);
			});
		});
	}

	function analysisFinished(res) {
		return !!res.history_id && (res.status === 'done' || parseInt(res.progress, 10) >= 100);
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
		});
	}

	document.getElementById('titlo-search-btn').onclick = function () {
		var wantedType = entityType();
		var q = (document.getElementById('titlo-search-q').value || '').trim();
		post('search', {entity_type: wantedType, q: q})
			.then(function (res) {
				var box = document.getElementById('titlo-search-results');
				if (!res.ok) {
					setStatus('titlo-analysis-status', res.error || 'Ошибка поиска', true);
					return;
				}
				box.innerHTML = '';
				var items = (res.items || []).filter(function (item) {
					return String(item.type || wantedType) === wantedType;
				});
				var typeLabel = wantedType === 'S' ? 'категория' : 'товар';
				if (!items.length) {
					clearEntitySelection();
					box.style.display = 'none';
					setStatus(
						'titlo-analysis-status',
						(wantedType === 'S' ? 'Категория' : 'Товар') + ' по запросу «' + q + '» не найден(а)',
						true
					);
					return;
				}
				function pick(item) {
					if (String(item.type) !== wantedType) {
						setStatus('titlo-analysis-status', 'Тип результата не совпал с выбранным', true);
						return;
					}
					document.getElementById('titlo-entity-id').value = item.id;
					document.getElementById('titlo-entity-label').textContent =
						(wantedType === 'S' ? 'Категория' : 'Товар') + ' #' + item.id + ' — ' + item.name;
					box.style.display = 'none';
					post('load_entity', {entity_type: wantedType, entity_id: item.id}).then(function (r) {
						if (!r.ok || !r.entity) {
							clearEntitySelection();
							setStatus('titlo-analysis-status', r.error || (typeLabel + ' не найдена'), true);
							return;
						}
						if (String(r.entity.type || wantedType) !== wantedType) {
							clearEntitySelection();
							setStatus('titlo-analysis-status', 'Ответ сервера: неверный тип сущности', true);
							return;
						}
						document.getElementById('titlo-url').value = r.entity.url || '';
						document.getElementById('titlo-phrase').value = r.entity.preferred_phrase
							|| r.entity.titlo_phrase
							|| (r.entity.name || '').substring(0, 50);
						if (r.entity.preview_text) document.getElementById('titlo-text-preview').value = r.entity.preview_text;
						if (r.entity.detail_text) document.getElementById('titlo-text-detail').value = r.entity.detail_text;
						if (r.entity.description) document.getElementById('titlo-text-category').value = r.entity.description;
						updateProdLinks();
						setStatus('titlo-analysis-status', 'Выбрана ' + typeLabel + ' #' + item.id);
						return loadLandingStats();
					});
				}
				items.forEach(function (item) {
					var div = document.createElement('div');
					div.textContent = (wantedType === 'S' ? 'Категория' : 'Товар') +
						' #' + item.id + ' — ' + item.name + (item.code ? (' (' + item.code + ')') : '');
					div.onclick = function () { pick(item); };
					box.appendChild(div);
				});
				box.style.display = 'block';
				// точный ID — берём сразу, без лишнего клика
				if (/^\d+$/.test(q) && items.length === 1 && String(items[0].id) === q) {
					pick(items[0]);
				}
			});
	};

	function clearEntitySelection() {
		document.getElementById('titlo-entity-id').value = '0';
		document.getElementById('titlo-entity-label').textContent = '—';
		document.getElementById('titlo-url').value = '';
		document.getElementById('titlo-phrase').value = '';
		var preview = document.getElementById('titlo-text-preview');
		var detail = document.getElementById('titlo-text-detail');
		var cat = document.getElementById('titlo-text-category');
		if (preview) preview.value = '';
		if (detail) detail.value = '';
		if (cat) cat.value = '';
		updateProdLinks();
	}

	Array.prototype.forEach.call(document.querySelectorAll('input[name="entity_type"]'), function (radio) {
		radio.addEventListener('change', function () {
			clearEntitySelection();
			document.getElementById('titlo-search-results').style.display = 'none';
			document.getElementById('titlo-search-results').innerHTML = '';
			setStatus('titlo-analysis-status', radio.value === 'S'
				? 'Режим: категории — введите ID/название категории'
				: 'Режим: товары — введите ID/название товара');
		});
	});

	document.getElementById('titlo-search-q').addEventListener('keydown', function (ev) {
		if (ev.key === 'Enter') {
			ev.preventDefault();
			document.getElementById('titlo-search-btn').click();
		}
	});

	document.getElementById('titlo-start-analysis').onclick = function () {
		var engine = (document.getElementById('titlo-engine') || {}).value || 'yandex';
		var region = (document.getElementById('titlo-region') || {}).value || '213';
		var top = (document.getElementById('titlo-top') || {}).value || '20';
		var regionLabel = (document.getElementById('titlo-region-q') || {}).value || regionLabelById(region);
		try {
			localStorage.setItem('titlo_analysis_params_v1', JSON.stringify({
				engine: engine,
				region: region,
				top: top,
				regionsByEngine: savedRegionByEngine
			}));
		} catch (e) {}
		setStatus('titlo-analysis-status', 'Запуск… ' + engine + ' / ' + (regionLabel || region) + ' / ТОП-' + top);
		renderScores(null);
		post('start_analysis', {
			url: document.getElementById('titlo-url').value,
			phrase: document.getElementById('titlo-phrase').value,
			engine: engine,
			region: region,
			top: top
		}).then(function poll(res) {
			if (!res.ok) {
				setStatus('titlo-analysis-status', res.error || 'Ошибка', true);
				return;
			}
			if (res.crawl_url && res.crawl_url !== document.getElementById('titlo-url').value) {
				setStatus('titlo-analysis-status', 'Краул: ' + res.crawl_url + ' · ' + engine + ' / ТОП-' + top);
			}
			if (res.history_id) {
				document.getElementById('titlo-history-id').value = res.history_id;
			}
			if (res.status === 'failed') {
				setStatus('titlo-analysis-status', res.error || 'Анализ упал', true);
				return;
			}
			if (analysisFinished(res) && res.history_id) {
				historyId = res.history_id;
				setStatus('titlo-analysis-status', 'Готово, history_id=' + historyId);
				return post('get_history', {history_id: historyId}).then(function (h) {
					if (h && h.ok) {
						return recordWorkScore(h, 'auto');
					}
				}).then(function () {
					return loadLandingStats();
				});
			}
			if (res.status === 'done' && !res.history_id) {
				setStatus('titlo-analysis-status', 'Анализ завершён, но history_id пустой — попробуйте ещё раз', true);
				return;
			}
			setStatus('titlo-analysis-status', 'Статус: ' + res.status + ' (' + (res.progress || 0) + '%)');
			return new Promise(function (resolve) { setTimeout(resolve, 3000); })
				.then(function () {
					return post('poll_analysis', {analysis_id: res.analysis_id});
				})
				.then(poll);
		});
	};

	document.getElementById('titlo-load-history').onclick = function () {
		historyId = parseInt(document.getElementById('titlo-history-id').value, 10) || 0;
		if (!historyId) {
			setStatus('titlo-analysis-status', 'Укажите history_id', true);
			return;
		}
		setStatus('titlo-analysis-status', 'Загрузка…');
		loadHistoryBundle(historyId).then(function () {
			setStatus('titlo-analysis-status', 'Фразы и баллы загружены');
		});
	};

	document.getElementById('titlo-tfidf-clouds-btn').onclick = function () {
		loadAndShowClouds();
	};

	(function bindCloudTips() {
		var tip = ensureCloudTip();
		document.addEventListener('mouseover', function (e) {
			var t = e.target;
			if (!t || !t.classList || !t.classList.contains('titlo-spiral-cloud__word')) return;
			var text = t.getAttribute('data-tip');
			if (!text) return;
			tip.textContent = text;
			tip.classList.add('is-on');
			tip.style.left = (e.pageX + 12) + 'px';
			tip.style.top = (e.pageY + 12) + 'px';
		});
		document.addEventListener('mousemove', function (e) {
			if (!tip.classList.contains('is-on')) return;
			tip.style.left = (e.pageX + 12) + 'px';
			tip.style.top = (e.pageY + 12) + 'px';
		});
		document.addEventListener('mouseout', function (e) {
			var t = e.target;
			if (!t || !t.classList || !t.classList.contains('titlo-spiral-cloud__word')) return;
			tip.classList.remove('is-on');
		});
	})();

	Array.prototype.forEach.call(document.querySelectorAll('.titlo-gen-btn'), function (btn) {
		btn.onclick = function () {
			var type = btn.getAttribute('data-type');
			var sel = document.querySelector('.titlo-prompt-select[data-type="' + type + '"]');
			var promptId = sel ? (parseInt(sel.value, 10) || 0) : 0;
			var promptName = sel && sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
			rebuildKeywordsFromTlp();
			var kwN = keywords.length;
			setStatus(
				'titlo-gen-status',
				'Генерация ' + type + (promptName ? ' («' + promptName.split(' — ')[0] + '»)' : '') +
				'… + TLP ' + kwN + ' слов'
			);
			post('start_generate', {
				type: type,
				url: document.getElementById('titlo-url').value,
				keywords: JSON.stringify(keywords),
				history_id: historyId || document.getElementById('titlo-history-id').value || '',
				prompt_id: promptId
			}).then(function poll(res) {
				if (!res.ok) {
					setStatus('titlo-gen-status', res.error || 'Ошибка', true);
					return;
				}
				if (res.status === 'completed') {
					var map = {preview: 'titlo-text-preview', detail: 'titlo-text-detail', category: 'titlo-text-category'};
					document.getElementById(map[type]).value = res.result || '';
					setStatus('titlo-gen-status', 'Готово: ' + type);
					loadPromptSelects();
					return;
				}
				if (res.status === 'failed') {
					setStatus('titlo-gen-status', res.error || 'Генерация не удалась', true);
					return;
				}
				setStatus('titlo-gen-status', 'Ожидание… (' + res.status + ')');
				return new Promise(function (resolve) { setTimeout(resolve, 2500); })
					.then(function () { return post('poll_generate', {record_id: res.record_id}); })
					.then(poll);
			});
		};
	});

	function fillPromptSelect(sel, payload) {
		if (!sel || !payload) return;
		var items = payload.items || [];
		var active = payload.active_id || 0;
		var last = payload.last;
		sel.innerHTML = '';
		items.forEach(function (it) {
			var opt = document.createElement('option');
			opt.value = String(it.id);
			var label = it.name;
			if (it.is_default) label += ' ★';
			label += ' — ' + (it.last_used_label || 'не использовался');
			opt.textContent = label;
			if (it.id === active) opt.selected = true;
			sel.appendChild(opt);
		});
		var meta = sel.closest('.titlo-gen-row');
		meta = meta ? meta.querySelector('.titlo-prompt-meta') : null;
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
			if (meta && it) {
				meta.textContent = 'Выбран: «' + it.name + '» · был: ' + (it.last_used_label || 'никогда');
			}
		};
	}

	function loadPromptSelects() {
		return post('list_prompts', {}).then(function (res) {
			if (!res.ok || !res.by_type) return;
			Array.prototype.forEach.call(document.querySelectorAll('.titlo-prompt-select'), function (sel) {
				var t = sel.getAttribute('data-type');
				fillPromptSelect(sel, res.by_type[t]);
			});
		});
	}

	loadPromptSelects();

	['titlo-tlp-missing-limit', 'titlo-tlp-diff-limit'].forEach(function (id) {
		var el = document.getElementById(id);
		if (!el) return;
		el.addEventListener('input', function () {
			el.dataset.userTouched = '1';
			rebuildKeywordsFromTlp();
		});
		el.addEventListener('change', function () {
			el.dataset.userTouched = '1';
			rebuildKeywordsFromTlp();
		});
	});

	try {
		var saved = JSON.parse(localStorage.getItem('titlo_analysis_params_v1') || '{}') || {};
		if (saved.engine && document.getElementById('titlo-engine')) {
			document.getElementById('titlo-engine').value = saved.engine;
		}
		if (saved.top && document.getElementById('titlo-top')) {
			document.getElementById('titlo-top').value = String(saved.top);
		}
		if (saved.regionsByEngine) {
			savedRegionByEngine.yandex = saved.regionsByEngine.yandex || '';
			savedRegionByEngine.google = saved.regionsByEngine.google || '';
		} else if (saved.region) {
			// старый формат: один region — кладём в текущую/сохранённую ПС
			var eng = (saved.engine === 'google') ? 'google' : 'yandex';
			savedRegionByEngine[eng] = String(saved.region);
		}
		applyEngineRegion();
	} catch (e) {}

	document.getElementById('titlo-save').onclick = function () {
		var type = entityType();
		var id = parseInt(document.getElementById('titlo-entity-id').value, 10) || 0;
		if (!id) {
			setStatus('titlo-save-status', 'Сначала выберите товар/категорию', true);
			return;
		}
		var preview = document.getElementById('titlo-text-preview').value;
		var detail = document.getElementById('titlo-text-detail').value;
		var category = document.getElementById('titlo-text-category').value;
		var msg = type === 'S'
			? 'Записать DESCRIPTION категории #' + id + '?'
			: 'Записать PREVIEW_TEXT / DETAIL_TEXT товара #' + id + '?';
		if (!confirm(msg)) return;

		post('save', {
			entity_type: type,
			entity_id: id,
			preview_text: preview,
			detail_text: detail,
			description: category,
			phrase: document.getElementById('titlo-phrase').value,
			name: ((document.getElementById('titlo-entity-label') || {}).textContent || '').replace(/^#\d+\s*[—\-]\s*/, '').trim(),
			url: document.getElementById('titlo-url').value
		}).then(function (res) {
			if (res.ok) setStatus('titlo-save-status', 'Сохранено (тексты + фраза). Дальше — повторный анализ для «после».');
			else setStatus('titlo-save-status', res.error || 'Ошибка сохранения', true);
		});
	};

	// Автоподгрузка динамики, если товар уже выбран (из URL или prefill)
	var urlInput = document.getElementById('titlo-url');
	if (urlInput) {
		urlInput.addEventListener('input', updateProdLinks);
		urlInput.addEventListener('change', updateProdLinks);
	}
	updateProdLinks();
	if (urlInput && urlInput.value.trim()) {
		loadLandingStats();
	}
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
