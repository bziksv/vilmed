<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\Config;
use Titlo\Relevance\WorkHistory;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');
WorkHistory::ensureTables();

$APPLICATION->SetTitle('Titlo: история проработки товаров и категорий');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$preset = (string) ($_GET['preset'] ?? 'all');
$entityType = (string) ($_GET['entity_type'] ?? '');
$q = (string) ($_GET['q'] ?? '');
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-work">
	<p class="adm-info-message" style="display:block;max-width:980px">
		Журнал циклов проработки: баллы и параметры анализа релевантности <b>до</b> и <b>после</b> правок текстов.
		Запись создаётся автоматически со страницы «Проработка»: первый анализ → «до», сохранение текстов → статус «сохранено»,
		повторный анализ → «после» и статус «готово». Пресеты помогают найти незакрытые, улучшенные и ещё не начатые позиции.
	</p>

	<div class="presets" id="titlo-presets">
		<button type="button" class="preset-btn" data-preset="all">Все циклы</button>
		<button type="button" class="preset-btn" data-preset="not_done">Не завершены</button>
		<button type="button" class="preset-btn" data-preset="open">Только анализ «до»</button>
		<button type="button" class="preset-btn" data-preset="saved">Сохранены, ждут пересчёт</button>
		<button type="button" class="preset-btn" data-preset="done">Готово (есть «после»)</button>
		<button type="button" class="preset-btn" data-preset="improved">Улучшили баллы</button>
		<button type="button" class="preset-btn" data-preset="worsened">Ухудшили</button>
		<button type="button" class="preset-btn" data-preset="week">За 7 дней</button>
		<button type="button" class="preset-btn" data-preset="products">Товары</button>
		<button type="button" class="preset-btn" data-preset="sections">Категории</button>
		<button type="button" class="preset-btn" data-preset="never">Ещё не начинали</button>
	</div>

	<div class="filters">
		<label>Тип:
			<select id="titlo-entity-type">
				<option value="" <?= $entityType === '' ? 'selected' : '' ?>>Все</option>
				<option value="E" <?= $entityType === 'E' ? 'selected' : '' ?>>Товары</option>
				<option value="S" <?= $entityType === 'S' ? 'selected' : '' ?>>Категории</option>
			</select>
		</label>
		<input type="text" id="titlo-q" class="adm-input" placeholder="ID / название / фраза / history_id" value="<?= htmlspecialcharsbx($q) ?>" style="width:280px">
		<input type="button" id="titlo-reload" class="adm-btn" value="Показать">
		<span id="titlo-list-status"></span>
	</div>

	<table>
		<thead>
		<tr>
			<th>Обновлено</th>
			<th>Тип / ID</th>
			<th>Режим</th>
			<th>Промпт</th>
			<th>Название / фраза</th>
			<th>Статус</th>
			<th>До (баллы)</th>
			<th>После</th>
			<th>Δ</th>
			<th>Параметры ПС</th>
			<th></th>
		</tr>
		</thead>
		<tbody id="titlo-work-body">
		<tr><td colspan="9">Загрузка…</td></tr>
		</tbody>
	</table>
	<div class="pager" id="titlo-pager"></div>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var preset = <?= json_encode($preset) ?>;
	var page = 1;

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.sessid = sessid;
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: new URLSearchParams(data).toString(),
			credentials: 'same-origin'
		}).then(function (r) { return r.json(); });
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
		});
	}
	function safeHref(u) {
		u = String(u || '').trim();
		if (!u) return '#';
		if (u.charAt(0) === '/') return escapeHtml(u);
		if (/^https?:\/\//i.test(u)) return escapeHtml(u);
		return '#';
	}
	function fmtNum(v) {
		if (v == null || v === '') return '—';
		return String(v);
	}
	function fmtDate(s) {
		if (!s) return '—';
		return String(s).replace('T', ' ').slice(0, 16);
	}
	function modeBadge(it) {
		if (!it || it.status === 'never' || !it.id) return '—';
		var mode = (it && it.run_mode) ? String(it.run_mode) : 'full';
		var label = (it && it.run_mode_label) ? String(it.run_mode_label) : mode;
		var cls = 'badge-mode-full';
		if (mode === 'refine') cls = 'badge-mode-refine';
		else if (mode === 'analyze_only') cls = 'badge-mode-analyze';
		return '<span class="badge ' + cls + '">' + escapeHtml(label) + '</span>';
	}
	function promptCell(it) {
		if (!it || it.status === 'never' || !it.id) return '—';
		var name = (it.prompt_detail_name || '').trim();
		if (!name && it.prompt_detail_id) name = '#' + it.prompt_detail_id;
		if (!name) return '—';
		var html = '<div class="prompt-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</div>';
		var preview = (it.prompt_preview_name || '').trim();
		if (preview) {
			html += '<div class="meta">анонс: ' + escapeHtml(preview) + '</div>';
		}
		return html;
	}
	function statusBadge(st) {
		var map = {
			open: ['badge-open', 'анализ «до»'],
			saved: ['badge-saved', 'сохранено'],
			done: ['badge-done', 'готово'],
			never: ['badge-never', 'не начинали']
		};
		var m = map[st] || ['badge-never', st || '—'];
		return '<span class="badge ' + m[0] + '">' + m[1] + '</span>';
	}
	function scoreCell(side) {
		if (!side || side.points == null) return '—';
		var html = '<div class="score"><b>' + escapeHtml(fmtNum(side.points)) + '</b>';
		if (side.points_ideal != null) html += ' / ' + escapeHtml(fmtNum(side.points_ideal));
		html += '</div>';
		html += '<div class="meta">поз. ' + escapeHtml(fmtNum(side.position)) +
			' · покр. ' + escapeHtml(fmtNum(side.coverage)) +
			' · пл. ' + escapeHtml(fmtNum(side.density)) + '</div>';
		if (side.history_id) html += '<div class="meta">#' + side.history_id + '</div>';
		return html;
	}
	function paramsCell(row) {
		var side = (row.after && row.after.history_id) ? row.after : row.before;
		if (!side) return '—';
		var eng = side.engine === 'google' ? 'Google' : (side.engine ? 'Яндекс' : '—');
		return '<div class="params">' + escapeHtml(eng) +
			'<br>рег. ' + escapeHtml(fmtNum(side.region)) +
			'<br>ТОП-' + escapeHtml(fmtNum(side.top)) + '</div>';
	}
	function deltaHtml(d) {
		if (d == null || d === '') return '—';
		var n = parseFloat(d);
		if (isNaN(n)) return '—';
		var cls = n > 0 ? 'delta-up' : (n < 0 ? 'delta-down' : 'delta-zero');
		var sign = n > 0 ? '+' : '';
		return '<span class="' + cls + '">' + sign + n + '</span>';
	}
	function markPresets() {
		document.querySelectorAll('#titlo-presets .preset-btn').forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-preset') === preset);
		});
	}
	function load() {
		markPresets();
		document.getElementById('titlo-list-status').textContent = 'Загрузка…';
		post('list_work_history', {
			preset: preset,
			entity_type: document.getElementById('titlo-entity-type').value,
			q: document.getElementById('titlo-q').value,
			page: page,
			page_size: 25
		}).then(function (res) {
			var body = document.getElementById('titlo-work-body');
			if (!res.ok) {
				body.innerHTML = '<tr><td colspan="11">' + escapeHtml(res.error || 'Ошибка') + '</td></tr>';
				document.getElementById('titlo-list-status').textContent = '';
				return;
			}
			var items = res.items || [];
			if (!items.length) {
				body.innerHTML = '<tr><td colspan="11">Ничего не найдено</td></tr>';
			} else {
				body.innerHTML = items.map(function (it) {
					var typeLabel = it.entity_type === 'S' ? 'Кат.' : 'Тов.';
					var name = escapeHtml(it.name || '—');
					var phrase = escapeHtml(it.phrase || '');
					var linkSite = it.url ? (' · <a href="' + safeHref(it.url) + '" target="_blank" rel="noopener">сайт</a>') : '';
					return '<tr>' +
						'<td>' + escapeHtml(fmtDate(it.updated_at || it.created_at)) + '</td>' +
						'<td>' + typeLabel + ' #' + it.entity_id +
							(it.id ? ('<div class="meta">цикл #' + it.id + '</div>') : '') + '</td>' +
						'<td>' + modeBadge(it) + '</td>' +
						'<td>' + promptCell(it) + '</td>' +
						'<td><div class="name-full">' + name + '</div>' +
							'<div class="meta">' + (phrase ? ('фраза: ' + phrase) : 'фраза не задана') + linkSite + '</div></td>' +
						'<td>' + statusBadge(it.status) + '</td>' +
						'<td>' + scoreCell(it.before) + '</td>' +
						'<td>' + scoreCell(it.after) + '</td>' +
						'<td>' + deltaHtml(it.delta_points) + '</td>' +
						'<td>' + paramsCell(it) + '</td>' +
						'<td><a class="adm-btn" href="' + escapeHtml(it.single_url || '#') + '">Проработка</a></td>' +
						'</tr>';
				}).join('');
			}
			var total = res.total || 0;
			var pages = Math.max(1, Math.ceil(total / (res.page_size || 25)));
			document.getElementById('titlo-list-status').textContent =
				'Найдено: ' + total + (res.note ? (' · ' + res.note) : '');
			var pager = document.getElementById('titlo-pager');
			pager.innerHTML = '';
			if (pages > 1) {
				pager.innerHTML =
					'<button type="button" class="adm-btn" id="titlo-prev"' + (page <= 1 ? ' disabled' : '') + '>←</button> ' +
					'стр. ' + page + ' / ' + pages + ' ' +
					'<button type="button" class="adm-btn" id="titlo-next"' + (page >= pages ? ' disabled' : '') + '>→</button>';
				var prev = document.getElementById('titlo-prev');
				var next = document.getElementById('titlo-next');
				if (prev) prev.onclick = function () { if (page > 1) { page--; load(); } };
				if (next) next.onclick = function () { if (page < pages) { page++; load(); } };
			}
		}).catch(function (e) {
			document.getElementById('titlo-work-body').innerHTML =
				'<tr><td colspan="11">' + escapeHtml(e.message || 'Сеть') + '</td></tr>';
			document.getElementById('titlo-list-status').textContent = '';
		});
	}

	document.querySelectorAll('#titlo-presets .preset-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			preset = btn.getAttribute('data-preset') || 'all';
			page = 1;
			load();
		});
	});
	document.getElementById('titlo-reload').onclick = function () { page = 1; load(); };
	document.getElementById('titlo-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { page = 1; load(); }
	});
	load();
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
