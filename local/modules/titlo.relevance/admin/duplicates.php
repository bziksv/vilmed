<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\Config;
use Titlo\Relevance\IndexingControl;
use Titlo\Relevance\ProductDuplicates;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');
ProductDuplicates::ensureFields();

$APPLICATION->SetTitle('Titlo: товары-дубли');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
$status = (string) ($_GET['status'] ?? 'open');
$q = (string) ($_GET['q'] ?? '');
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-dups">
	<?php
	$np = IndexingControl::resolveProperty();
	$writeBits = ['UF'];
	if (IndexingControl::useSeoRobots()) {
		if ($np) {
			$writeBits[] = 'свойство <code>' . htmlspecialcharsbx($np['code']) . '</code>';
		}
		$writeBits[] = 'SEO';
	}
	$writeHint = implode(' + ', $writeBits);
	?>
	<details class="titlo-howto">
		<summary>Как работают дубли товаров</summary>
		<div class="titlo-howto__body">
			<ul>
				<li>
					В каждом блоке — <strong>группа похожих карточек</strong>.
					Первая строка (зелёный фон) — эталон; у остальных — «похож на #ID на N%».
				</li>
				<li>
					<strong>Прорабатывать</strong> — канон.
					<strong>Закрыть от индексации</strong> — meta robots noindex на странице товара
					(<code>robots.txt</code> не меняем).
				</li>
				<li>
					<strong>Сбросить</strong> — убрать решение.
					<strong>Проработка</strong> — анализ релевантности и тексты по карточке.
				</li>
				<li>Куда пишется: <?= $writeHint ?>.</li>
				<li>
					Если решение «пропало» из открытых — фильтр
					<strong>«Отмеченные: прорабатывать»</strong>.
				</li>
			</ul>
		</div>
	</details>

	<div class="filters">
		<label>Показать:
			<select id="titlo-dup-status">
				<option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Кластеры: открытые</option>
				<option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Кластеры: решённые</option>
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Кластеры: все</option>
				<option value="keep" <?= $status === 'keep' ? 'selected' : '' ?>>Отмеченные: прорабатывать</option>
				<option value="noindex" <?= $status === 'noindex' ? 'selected' : '' ?>>Отмеченные: noindex</option>
				<option value="decided" <?= $status === 'decided' ? 'selected' : '' ?>>Все с решением</option>
			</select>
		</label>
		<input type="text" id="titlo-dup-q" class="adm-input" placeholder="ID / название / код" value="<?= htmlspecialcharsbx($q) ?>" style="width:260px">
		<label>Порог %:
			<input type="number" id="titlo-dup-score" class="adm-input" value="<?= (int) ProductDuplicates::SIMILARITY_MIN ?>" min="50" max="99" style="width:70px">
		</label>
		<input type="button" id="titlo-dup-reload" class="adm-btn" value="Показать">
		<span id="titlo-dup-list-status"></span>
	</div>

	<div id="titlo-dup-hint" class="hint-lost" style="display:none"></div>
	<div id="titlo-dup-body">Загрузка…</div>
	<div class="pager" id="titlo-dup-pager"></div>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var page = 1;
	var iblockId = <?= (int) Config::iblockId() ?>;

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
		return String(s).replace(/[&<>"']/g, function (c) {
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

	function decisionBadge(item) {
		if (item.decision === 'keep') return '<span class="badge badge-keep">прорабатывать</span>';
		if (item.decision === 'noindex' || item.noindex) return '<span class="badge badge-noindex">noindex</span>';
		return '<span class="badge badge-none">не решено</span>';
	}

	function actBtn(html, tip) {
		return '<span class="titlo-act">' + html +
			'<span class="titlo-act__tip">' + escapeHtml(tip) + '</span></span> ';
	}

	function renderRow(item, isTop) {
		var closed = item.decision === 'noindex' || item.noindex;
		var indexBtn = closed
			? actBtn(
				'<input type="button" class="adm-btn titlo-dup-open" value="Открыть для индексации">',
				'Снова разрешить индексацию: снять noindex со страницы товара.'
			)
			: actBtn(
				'<input type="button" class="adm-btn titlo-dup-noindex" value="Закрыть от индексации">',
				'Дубль убрать из поиска: meta robots = noindex, nofollow на странице товара. robots.txt не меняется.'
			);
		return '<tr data-id="' + item.id + '"' + (isTop ? ' class="is-top"' : '') + '>' +
			'<td><a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' + iblockId + '&type=catalog&ID=' + item.id + '&lang=<?= LANGUAGE_ID ?>" target="_blank">' + item.id + '</a></td>' +
			'<td><div>' + escapeHtml(item.name) + decisionBadge(item) + '</div>' +
			(item.sim_label ? '<div class="sim">' + escapeHtml(item.sim_label) + '</div>' : '') +
			(item.phrase ? '<div class="name-len">фраза: ' + escapeHtml(item.phrase) + '</div>' : '') +
			'<div class="name-len"><a href="' + safeHref(item.url) + '" target="_blank" rel="noopener">на сайте</a></div></td>' +
			'<td class="row-actions">' +
			actBtn(
				'<input type="button" class="adm-btn-save titlo-dup-keep" value="Прорабатывать">',
				'Отметить как канон: эту карточку оставляем в работе / для SEO. Решение сохранится в фильтре «Отмеченные: прорабатывать».'
			) +
			indexBtn +
			actBtn(
				'<input type="button" class="adm-btn titlo-dup-clear" value="Сбросить">',
				'Снять решение: убрать «прорабатывать» / noindex, вернуть статус «не решено» и снова открыть для индексации.'
			) +
			actBtn(
				'<a class="adm-btn" href="titlo_relevance_single.php?lang=<?= LANGUAGE_ID ?>&ENTITY=E&ID=' + item.id + '">Проработка</a>',
				'Открыть страницу проработки: анализ релевантности и генерация текстов для этой карточки.'
			) +
			'<span class="status row-status"></span></td></tr>';
	}

	function renderPager(res) {
		var pager = document.getElementById('titlo-dup-pager');
		pager.innerHTML = '';
		var pages = res.pages || 1;
		if (pages > 1) {
			for (var i = 1; i <= pages && i <= 40; i++) {
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
	}

	function load(opts) {
		opts = opts || {};
		var status = document.getElementById('titlo-dup-status').value;
		var q = document.getElementById('titlo-dup-q').value;
		var minScore = document.getElementById('titlo-dup-score').value;
		document.getElementById('titlo-dup-list-status').textContent = 'Загрузка…';
		post('list_duplicates', {status: status, q: q, page: page, page_size: 15, min_score: minScore}).then(function (res) {
			var body = document.getElementById('titlo-dup-body');
			var hint = document.getElementById('titlo-dup-hint');
			if (!res.ok) {
				body.textContent = res.error || 'Ошибка';
				return;
			}

			if (res.mode === 'decisions') {
				document.getElementById('titlo-dup-list-status').textContent = 'Карточек: ' + res.total;
				if (!opts.keepHint) hint.style.display = 'none';
				if (!res.items || !res.items.length) {
					body.innerHTML = '<p>Нет отмеченных карточек. Нажмите «Прорабатывать» / «Закрыть» в кластере.</p>';
					document.getElementById('titlo-dup-pager').innerHTML = '';
					return;
				}
				body.innerHTML = '<div class="cluster"><div class="cluster-h">Список решений (вне кластеров тоже видны)</div>' +
					'<table><thead><tr><th style="width:80px">ID</th><th>Название</th><th style="width:400px">Решение</th></tr></thead><tbody>' +
					res.items.map(function (item) { return renderRow(item, item.decision === 'keep'); }).join('') +
					'</tbody></table></div>';
				renderPager(res);
				return;
			}

			document.getElementById('titlo-dup-list-status').textContent = 'Групп: ' + res.total;
			if (!res.clusters || !res.clusters.length) {
				body.innerHTML = '<p>Ничего не найдено. Проверьте ID, поиск по названию, фильтр «Отмеченные: прорабатывать» или понизьте порог %.</p>';
				document.getElementById('titlo-dup-pager').innerHTML = '';
				return;
			}
			if (!opts.keepHint) hint.style.display = 'none';
			body.innerHTML = res.clusters.map(function (cl, idx) {
				var stClass = cl.status === 'resolved' ? 'st-resolved' : 'st-open';
				var stLabel = cl.status === 'resolved' ? 'решён' : 'нужно решить';
				if (cl.single || cl.size < 2) {
					stLabel = 'без пары дублей';
					stClass = 'name-len';
				}
				var ids = (cl.items || []).map(function (it) { return it.id; });
				var keepId = ids.length ? ids[0] : 0;
				var bulkBtn = (cl.size >= 2)
					? ('<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-dup-cluster-noindex" value="Эталон + закрыть остальных">' +
						'<span class="titlo-act__tip">Первую карточку (зелёная) — «прорабатывать», все остальные в группе — закрыть от индексации.</span>' +
						'</span>' +
						'<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-dup-cluster-noindex-all" value="Закрыть весь блок">' +
						'<span class="titlo-act__tip">Закрыть от индексации ВСЕ карточки группы, включая эталон (зелёную строку).</span>' +
						'</span>')
					: '';
				var rows = cl.items.map(function (item, i) {
					return renderRow(item, i === 0);
				}).join('');
				return '<div class="cluster" data-key="' + escapeHtml(cl.key) + '" data-keep-id="' + keepId + '" data-ids="' + escapeHtml(ids.join(',')) + '">' +
					'<div class="cluster-h">' +
					'<span>Группа ' + (idx + 1 + (res.page - 1) * res.page_size) + '</span>' +
					(cl.size >= 2 ? '<span class="score">между собой ≥ ' + cl.score + '%</span>' : '') +
					'<span>' + cl.size + ' карточки</span>' +
					'<span class="' + stClass + '">' + stLabel + '</span>' +
					'<span class="name-len">стык по: ' + escapeHtml(cl.key) + '</span>' +
					bulkBtn +
					'</div>' +
					'<table><thead><tr><th style="width:80px">ID</th><th>Название / насколько похож на эталон</th><th style="width:400px">Решение</th></tr></thead><tbody>' +
					rows + '</tbody></table></div>';
			}).join('');
			renderPager(res);
		});
	}

	document.getElementById('titlo-dup-body').addEventListener('click', function (e) {
		var btn = e.target;
		if (btn.classList.contains('titlo-dup-cluster-noindex') || btn.classList.contains('titlo-dup-cluster-noindex-all')) {
			var cluster = btn.closest('.cluster');
			if (!cluster) return;
			var keepId = cluster.getAttribute('data-keep-id');
			var ids = cluster.getAttribute('data-ids') || '';
			var idList = ids.split(',').filter(function (x) { return !!x; });
			var closeAll = btn.classList.contains('titlo-dup-cluster-noindex-all');
			var others = idList.filter(function (x) { return x !== String(keepId); }).length;
			var confirmMsg = closeAll
				? ('Закрыть от индексации ВЕСЬ блок (' + idList.length + ' карточек), включая эталон #' + keepId + '?')
				: ('Эталон #' + keepId + ' — прорабатывать.\nОстальные (' + others + ') — закрыть от индексации?');
			if (!confirm(confirmMsg)) {
				return;
			}
			var labelKeep = 'Эталон + закрыть остальных';
			var labelAll = 'Закрыть весь блок';
			btn.disabled = true;
			btn.value = 'Сохраняю…';
			var hint = document.getElementById('titlo-dup-hint');
			hint.style.display = '';
			hint.innerHTML = closeAll
				? ('Закрываю весь блок (' + idList.length + ')…')
				: ('Сохраняю решения для группы (эталон #' + keepId + ', закрыть ' + others + ')…');
			var action = closeAll ? 'dup_cluster_noindex_all' : 'dup_cluster_noindex_others';
			var payload = closeAll ? {ids: ids} : {keep_id: keepId, ids: ids};
			post(action, payload).then(function (res) {
				btn.disabled = false;
				btn.value = closeAll ? labelAll : labelKeep;
				if (res.ok) {
					hint.innerHTML = closeAll
						? ('Готово: блок #' + (keepId || idList[0] || '') + ' закрыт (' + (res.noindex_count || 0) + ' карточек). Остаётесь в текущем списке — можно закрывать следующий.')
						: ('Готово: эталон <b>#' + keepId + '</b> — прорабатывать, закрыто остальных: <b>' + (res.noindex_count || 0) + '</b>. Список обновлён, фильтр не менялся.');
					load({ keepHint: true });
				} else {
					hint.innerHTML = '<span style="color:#c00">' + escapeHtml(res.error || 'Ошибка массового закрытия') + '</span>';
				}
			}).catch(function (err) {
				btn.disabled = false;
				btn.value = closeAll ? labelAll : labelKeep;
				hint.style.display = '';
				hint.innerHTML = '<span style="color:#c00">Сеть/ответ сервера: ' + escapeHtml((err && err.message) ? err.message : 'ошибка') + '</span>';
			});
			return;
		}
		if (!btn.classList.contains('titlo-dup-keep') && !btn.classList.contains('titlo-dup-noindex') && !btn.classList.contains('titlo-dup-clear') && !btn.classList.contains('titlo-dup-open')) return;
		var tr = btn.closest('tr');
		if (!tr) return;
		var id = tr.getAttribute('data-id');
		var statusEl = tr.querySelector('.row-status');
		var decision = 'clear';
		if (btn.classList.contains('titlo-dup-keep')) decision = 'keep';
		if (btn.classList.contains('titlo-dup-noindex')) decision = 'noindex';
		if (btn.classList.contains('titlo-dup-open')) decision = 'clear';
		statusEl.textContent = '…';
		post('dup_decision', {entity_id: id, decision: decision}).then(function (res) {
			statusEl.textContent = res.ok ? 'OK' : (res.error || 'Ошибка');
			statusEl.style.color = res.ok ? '#15803d' : '#c00';
			if (!res.ok) return;
			var hint = document.getElementById('titlo-dup-hint');
			hint.style.display = '';
			if (decision === 'keep') {
				hint.innerHTML = 'Сохранено «прорабатывать» для <b>#' + id + '</b>.';
			} else if (decision === 'noindex') {
				hint.innerHTML = 'Закрыто от индексации <b>#' + id + '</b>.';
			} else {
				hint.innerHTML = 'Сброшено решение для <b>#' + id + '</b>.';
			}
			load({ keepHint: true });
		});
	});

	document.getElementById('titlo-dup-reload').onclick = function () { page = 1; load(); };
	document.getElementById('titlo-dup-status').addEventListener('change', function () { page = 1; load(); });
	document.getElementById('titlo-dup-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { page = 1; load(); }
	});

	load();
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
