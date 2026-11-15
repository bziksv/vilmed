<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\Config;
use Titlo\Relevance\IndexingControl;
use Titlo\Relevance\SectionDuplicates;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('iblock');
Loader::includeModule('titlo.relevance');
SectionDuplicates::ensureFields();

$APPLICATION->SetTitle('Titlo: категории-дубли');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
$status = (string) ($_GET['status'] ?? 'open');
$q = (string) ($_GET['q'] ?? '');
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-sdups">
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
		<summary>Как работают дубли категорий</summary>
		<div class="titlo-howto__body">
			<ul>
				<li>
					В каждом блоке — <strong>группа дублей</strong>. Первая строка (зелёный фон) — эталон:
					раздел с решением «прорабатывать», иначе с наибольшим числом товаров, иначе ближе к корню.
					У остальных — почему они дубль (то же название / те же товары / похожие слова).
				</li>
				<li>
					<strong>Типы:</strong>
					«одинаковые названия» — то же название в другой ветке (часто пустые копии, 0 товаров);
					«одни и те же товары» — разные названия, но товары совпадают ≥ 80%;
					«похожие названия» — слова совпадают на порог % и выше;
					«одно название — разные товары» — тёзки с разным наполнением: лучше переименовать, чем закрывать.
				</li>
				<li>
					Разделы, которые не являются отдельной страницей, в дубли не попадают:
					редирект в <code>.htaccess</code> (<code>Redirect</code>, <code>RedirectMatch</code>, <code>RewriteRule [R]</code>)
					и фактический HTTP-ответ страницы — 3xx (nginx, Apache, PHP, SEO-модули) или canonical на другой URL.
					HTTP-проверка кешируется на 7 дней; «Перепроверить URL» — сбросить кеш.
					<strong>Линейки</strong> — подразделы одного родителя, которые отличаются только размером/серией
					(0, 2/0, М 1,5) или страной («ИВЛ, Россия» / «ИВЛ, США», «X» → «X, Япония»):
					эталон — общий родитель, варианты можно закрыть одной кнопкой.
					Разные бренды/модели («Мониторы» / «Мониторы COMEN») дублями не считаются.
				</li>
				<li>
					Строка с пометкой <span class="badge badge-distinct">другие товары</span> — в группе по названию,
					но наполнение другое; «Эталон + закрыть остальных» её не закрывает.
				</li>
				<li>
					<strong>Прорабатывать</strong> — канон.
					<strong>Закрыть от индексации</strong> — meta robots noindex на странице раздела
					(<code>robots.txt</code> не меняем).
				</li>
				<li>
					<strong>Сбросить</strong> — убрать решение.
					<strong>Проработка</strong> — анализ релевантности и тексты по категории.
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
			<select id="titlo-sdup-status">
				<option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Кластеры: открытые</option>
				<option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Кластеры: решённые</option>
				<option value="ignored" <?= $status === 'ignored' ? 'selected' : '' ?>>Кластеры: оставлены как есть</option>
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Кластеры: все</option>
				<option value="keep" <?= $status === 'keep' ? 'selected' : '' ?>>Отмеченные: прорабатывать</option>
				<option value="noindex" <?= $status === 'noindex' ? 'selected' : '' ?>>Отмеченные: noindex</option>
				<option value="decided" <?= $status === 'decided' ? 'selected' : '' ?>>Все с решением</option>
			</select>
		</label>
		<label>Тип:
			<select id="titlo-sdup-kind">
				<option value="all">все</option>
				<option value="names">одинаковые названия</option>
				<option value="products">одни и те же товары</option>
				<option value="similar">похожие названия</option>
				<option value="homonym">одно название — разные товары</option>
				<option value="variants">линейки (размеры/серии/страны)</option>
			</select>
		</label>
		<label title="Откуда брать H1 раздела. По умолчанию — SEO-поле «Заголовок раздела» инфоблока.">H1 из:
			<select id="titlo-sdup-h1">
				<?php $h1Src = SectionDuplicates::h1Source(); ?>
				<?php foreach (SectionDuplicates::h1SourceOptions() as $k => $label): ?>
					<option value="<?= htmlspecialcharsbx($k) ?>" <?= $k === $h1Src ? 'selected' : '' ?>><?= htmlspecialcharsbx($label) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<input type="text" id="titlo-sdup-q" class="adm-input" placeholder="ID / название / код" value="<?= htmlspecialcharsbx($q) ?>" style="width:260px">
		<label>Порог %:
			<input type="number" id="titlo-sdup-score" class="adm-input" value="<?= (int) SectionDuplicates::SIMILARITY_MIN ?>" min="50" max="99" style="width:70px">
		</label>
		<input type="button" id="titlo-sdup-reload" class="adm-btn" value="Показать">
		<input type="button" id="titlo-sdup-probe-reset" class="adm-btn" value="Перепроверить URL" title="Сбросить кеш HTTP-проверки (редиректы, canonical) и проверить заново">

		<span id="titlo-sdup-list-status"></span>
	</div>

	<div id="titlo-sdup-hint" class="hint-lost" style="display:none"></div>
	<div id="titlo-sdup-body">Загрузка…</div>
	<div class="pager" id="titlo-sdup-pager"></div>
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
		if (u.indexOf('//') === 0) return '#';
		if (u.charAt(0) === '/') return escapeHtml(u);
		if (/^https?:\/\//i.test(u)) return escapeHtml(u);
		return '#';
	}

	function decisionBadge(item) {
		if (item.decision === 'keep') return '<span class="badge badge-keep">прорабатывать</span>';
		if (item.decision === 'noindex' || item.noindex) return '<span class="badge badge-noindex">noindex</span>';
		return '<span class="badge badge-none">не решено</span>';
	}

	var probeRetries = 0;
	function probeText(p) {
		if (!p) return '';
		var s = '';
		if (p.pending) s += '; проверяю URL по HTTP, осталось ' + p.pending + '…';
		if (p.blocked) s += '; антибот отдал заглушку на ' + p.blocked + ' URL — задайте cookie в настройках модуля';
		return s;
	}
	function scheduleProbeReload(p) {
		if (p && p.pending && probeRetries < 200) {
			probeRetries++;
			setTimeout(function () { load({ keepHint: true }); }, 500);
		} else {
			probeRetries = 0;
		}
	}

	function productsBadge(item) {
		if (typeof item.products !== 'number') return '';
		if (item.products === 0) return '<span class="badge badge-empty">пустой</span>';
		return '<span class="badge badge-products">товаров: ' + item.products + '</span>';
	}

	function actBtn(html, tip) {
		return '<span class="titlo-act">' + html +
			'<span class="titlo-act__tip">' + escapeHtml(tip) + '</span></span> ';
	}

	function h1Line(item, isTop, refH1) {
		if (typeof item.h1 === 'undefined') return '';
		var h1 = String(item.h1 || '');
		var same = !isTop && h1 !== '' && refH1 && h1.toLowerCase() === String(refH1).toLowerCase();
		return '<div class="sdup-h1"><b>H1:</b> ' + (h1 !== '' ? escapeHtml(h1) : '<i>пусто</i>') +
			(same ? ' <span class="badge badge-distinct">H1 как у эталона</span>' : '') + '</div>';
	}

	function renderRow(item, isTop, refH1) {
		var closed = item.decision === 'noindex' || item.noindex;
		var indexBtn = closed
			? actBtn(
				'<input type="button" class="adm-btn titlo-sdup-open" value="Открыть для индексации">',
				'Снова разрешить индексацию: снять noindex со страницы раздела.'
			)
			: actBtn(
				'<input type="button" class="adm-btn titlo-sdup-noindex" value="Закрыть от индексации">',
				'Дубль убрать из поиска: meta robots = noindex, nofollow на странице раздела. robots.txt не меняется.'
			);
		return '<tr data-id="' + item.id + '"' + (isTop ? ' class="is-top"' : '') + '>' +
			'<td><a href="/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=' + iblockId + '&type=catalog&ID=' + item.id + '&lang=<?= LANGUAGE_ID ?>" target="_blank">' + item.id + '</a></td>' +
			'<td><div>' + escapeHtml(item.name) + decisionBadge(item) + productsBadge(item) +
			(item.distinct ? '<span class="badge badge-distinct">другие товары</span>' : '') + '</div>' +
			(item.path ? '<div class="sdup-path">' + escapeHtml(item.path) + '</div>' : '') +
			h1Line(item, isTop, refH1) +
			(item.sim_label ? '<div class="sim">' + escapeHtml(item.sim_label) + '</div>' : '') +
			(item.url_note ? '<div class="sim" style="color:#b45309">' + escapeHtml(item.url_note) + '</div>' : '') +
			'<div class="name-len"><a href="' + safeHref(item.url) + '" target="_blank" rel="noopener">на сайте</a></div></td>' +
			'<td class="row-actions">' +
			actBtn(
				'<input type="button" class="adm-btn-save titlo-sdup-keep" value="Прорабатывать">',
				'Отметить как канон: этот раздел оставляем в работе / для SEO. Решение сохранится в фильтре «Отмеченные: прорабатывать».'
			) +
			indexBtn +
			actBtn(
				'<input type="button" class="adm-btn titlo-sdup-clear" value="Сбросить">',
				'Снять решение: убрать «прорабатывать» / noindex, вернуть статус «не решено» и снова открыть для индексации.'
			) +
			actBtn(
				'<a class="adm-btn" href="titlo_relevance_single.php?lang=<?= LANGUAGE_ID ?>&ENTITY=S&ID=' + item.id + '">Проработка</a>',
				'Открыть страницу проработки: анализ релевантности и генерация текстов для этой категории.'
			) +
			'<span class="status row-status"></span></td></tr>';
	}

	function renderPager(res) {
		var pager = document.getElementById('titlo-sdup-pager');
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
		var status = document.getElementById('titlo-sdup-status').value;
		var q = document.getElementById('titlo-sdup-q').value;
		var minScore = document.getElementById('titlo-sdup-score').value;
		var kind = document.getElementById('titlo-sdup-kind').value;
		document.getElementById('titlo-sdup-list-status').textContent = 'Загрузка…';
		post('list_section_duplicates', {status: status, kind: kind, q: q, page: page, page_size: 15, min_score: minScore}).then(function (res) {
			var body = document.getElementById('titlo-sdup-body');
			var hint = document.getElementById('titlo-sdup-hint');
			if (!res.ok) {
				body.textContent = res.error || 'Ошибка';
				return;
			}

			if (res.mode === 'decisions') {
				document.getElementById('titlo-sdup-list-status').textContent = 'Категорий: ' + res.total;
				if (!opts.keepHint) hint.style.display = 'none';
				if (!res.items || !res.items.length) {
					body.innerHTML = '<p>Нет отмеченных категорий. Нажмите «Прорабатывать» / «Закрыть» в кластере.</p>';
					document.getElementById('titlo-sdup-pager').innerHTML = '';
					return;
				}
				body.innerHTML = '<div class="cluster"><div class="cluster-h">Список решений (вне кластеров тоже видны)</div>' +
					'<table><thead><tr><th style="width:80px">ID</th><th>Название</th><th style="width:400px">Решение</th></tr></thead><tbody>' +
					res.items.map(function (item) { return renderRow(item, item.decision === 'keep'); }).join('') +
					'</tbody></table></div>';
				renderPager(res);
				return;
			}

			var bk = res.open_by_kind || {};
			document.getElementById('titlo-sdup-list-status').textContent = 'Групп: ' + res.total +
				' (открытых: по названию ' + (bk.names || 0) + ', по товарам ' + (bk.products || 0) +
				', похожих ' + (bk.similar || 0) + ', тёзок ' + (bk.homonym || 0) + ', линеек ' + (bk.variants || 0) + ')' +
				(res.redirect_hidden ? '; скрыто — редирект/canonical на другую страницу: ' + res.redirect_hidden : '') +
				probeText(res.probe);
			scheduleProbeReload(res.probe);
			if (!res.clusters || !res.clusters.length) {
				body.innerHTML = '<p>Ничего не найдено. Проверьте ID, поиск по названию, фильтр «Отмеченные: прорабатывать» или понизьте порог %.</p>';
				document.getElementById('titlo-sdup-pager').innerHTML = '';
				return;
			}
			if (!opts.keepHint) hint.style.display = 'none';
			body.innerHTML = res.clusters.map(function (cl, idx) {
				var stClass = cl.status === 'resolved' ? 'st-resolved' : 'st-open';
				var stLabel = cl.status === 'resolved' ? 'решён' : 'нужно решить';
				if (cl.status === 'ignored') {
					stLabel = 'оставлен как есть';
					stClass = 'name-len';
				}
				if (cl.single || cl.size < 2) {
					stLabel = 'без пары дублей';
					stClass = 'name-len';
				}
				var ids = (cl.items || []).map(function (it) { return it.id; });
				var keepId = ids.length ? ids[0] : 0;
				var closeIds = (cl.items || []).filter(function (it, i) { return i === 0 || !it.distinct; })
					.map(function (it) { return it.id; });
				var ignoreBtn = '';
				if (cl.size >= 2 && cl.status === 'ignored') {
					ignoreBtn = '<span class="titlo-act"><input type="button" class="adm-btn titlo-dup-unignore" value="Вернуть в работу">' +
						'<span class="titlo-act__tip">Снова показывать группу в открытых.</span></span>';
				} else if (cl.size >= 2 && cl.status === 'open') {
					ignoreBtn = '<span class="titlo-act"><input type="button" class="adm-btn titlo-dup-ignore" value="Оставить как есть">' +
						'<span class="titlo-act__tip">Ничего не менять и скрыть группу из открытых. Найти и вернуть — фильтр «Кластеры: оставлены как есть». Если в группу добавится новый раздел, она снова станет открытой.</span></span>';
				}
				var bulkBtn = (cl.size >= 2 && cl.kind !== 'homonym')
					? ('<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-sdup-cluster-noindex" value="Эталон + закрыть остальных">' +
						'<span class="titlo-act__tip">Первую категорию (зелёная) — «прорабатывать», все остальные в группе — закрыть от индексации.</span>' +
						'</span>' +
						'<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-sdup-cluster-noindex-all" value="Закрыть весь блок">' +
						'<span class="titlo-act__tip">Закрыть от индексации ВСЕ категории группы, включая эталон (зелёную строку).</span>' +
						'</span>')
					: '';
				var refH1 = cl.items.length ? cl.items[0].h1 : '';
				var rows = cl.items.map(function (item, i) {
					return renderRow(item, i === 0, refH1);
				}).join('');
				return '<div class="cluster" data-key="' + escapeHtml(cl.key) + '" data-keep-id="' + keepId + '" data-ids="' + escapeHtml(ids.join(',')) + '" data-close-ids="' + escapeHtml(closeIds.join(',')) + '">' +
					'<div class="cluster-h">' +
					'<span>Группа ' + (idx + 1 + (res.page - 1) * res.page_size) + '</span>' +
					(cl.kind_label ? '<span class="badge badge-kind">' + escapeHtml(cl.kind_label) + '</span>' : '') +
					(cl.size >= 2 && cl.score < 100 ? '<span class="score">совпадение ≥ ' + cl.score + '%</span>' : '') +
					'<span>' + cl.size + ' категори' + (cl.size >= 5 ? 'й' : 'и') + '</span>' +
					(cl.empty_count ? '<span class="name-len">пустых: ' + cl.empty_count + '</span>' : '') +
					'<span class="' + stClass + '">' + stLabel + '</span>' +
					bulkBtn + ignoreBtn +
					'</div>' +
					'<table><thead><tr><th style="width:80px">ID</th><th>Название / насколько похож на эталон</th><th style="width:400px">Решение</th></tr></thead><tbody>' +
					rows + '</tbody></table></div>';
			}).join('');
			renderPager(res);
		});
	}

	document.getElementById('titlo-sdup-body').addEventListener('click', function (e) {
		var btn = e.target;
		if (btn.classList.contains('titlo-dup-ignore') || btn.classList.contains('titlo-dup-unignore')) {
			var cl = btn.closest('.cluster');
			if (!cl) return;
			var unignore = btn.classList.contains('titlo-dup-unignore');
			btn.disabled = true;
			post(unignore ? 'dup_unignore' : 'dup_ignore', {entity: 'S', ids: cl.getAttribute('data-ids') || ''}).then(function (res) {
				btn.disabled = false;
				var hint = document.getElementById('titlo-sdup-hint');
				hint.style.display = '';
				hint.innerHTML = res.ok
					? (unignore ? 'Группа возвращена в открытые.' : 'Группа оставлена как есть и скрыта. Вернуть — фильтр «Кластеры: оставлены как есть».')
					: '<span style="color:#c00">' + escapeHtml(res.error || 'Ошибка') + '</span>';
				if (res.ok) load({ keepHint: true });
			});
			return;
		}
		if (btn.classList.contains('titlo-sdup-cluster-noindex') || btn.classList.contains('titlo-sdup-cluster-noindex-all')) {
			var cluster = btn.closest('.cluster');
			if (!cluster) return;
			var keepId = cluster.getAttribute('data-keep-id');
			var closeAll = btn.classList.contains('titlo-sdup-cluster-noindex-all');
			var allIds = cluster.getAttribute('data-ids') || '';
			var ids = closeAll ? allIds : (cluster.getAttribute('data-close-ids') || allIds);
			var idList = ids.split(',').filter(function (x) { return !!x; });
			var skipped = allIds.split(',').filter(function (x) { return !!x; }).length - idList.length;
			var others = idList.filter(function (x) { return x !== String(keepId); }).length;
			var confirmMsg = closeAll
				? ('Закрыть от индексации ВЕСЬ блок (' + idList.length + ' категорий), включая эталон #' + keepId + '?')
				: ('Эталон #' + keepId + ' — прорабатывать.\nОстальные (' + others + ') — закрыть от индексации?' +
					(skipped > 0 ? '\nС другими товарами (' + skipped + ') — не трогаем.' : ''));
			if (!confirm(confirmMsg)) {
				return;
			}
			var labelKeep = 'Эталон + закрыть остальных';
			var labelAll = 'Закрыть весь блок';
			btn.disabled = true;
			btn.value = 'Сохраняю…';
			var hint = document.getElementById('titlo-sdup-hint');
			hint.style.display = '';
			hint.innerHTML = closeAll
				? ('Закрываю весь блок (' + idList.length + ')…')
				: ('Сохраняю решения для группы (эталон #' + keepId + ', закрыть ' + others + ')…');
			var action = closeAll ? 'section_dup_cluster_noindex_all' : 'section_dup_cluster_noindex_others';
			var payload = closeAll ? {ids: ids} : {keep_id: keepId, ids: ids};
			post(action, payload).then(function (res) {
				btn.disabled = false;
				btn.value = closeAll ? labelAll : labelKeep;
				if (res.ok) {
					hint.innerHTML = closeAll
						? ('Готово: блок #' + (keepId || idList[0] || '') + ' закрыт (' + (res.noindex_count || 0) + ' категорий). Остаётесь в текущем списке — можно закрывать следующий.')
						: ('Готово: эталон <b>#' + keepId + '</b> — прорабатывать, закрыто остальных: <b>' + (res.noindex_count || 0) + '</b>. Список обновлён, фильтр не менялся.');
					// Не прыгаем на «все + ID» — остаёмся в открытых и идём дальше по группам
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
		if (!btn.classList.contains('titlo-sdup-keep') && !btn.classList.contains('titlo-sdup-noindex') && !btn.classList.contains('titlo-sdup-clear') && !btn.classList.contains('titlo-sdup-open')) return;
		var tr = btn.closest('tr');
		if (!tr) return;
		var id = tr.getAttribute('data-id');
		var statusEl = tr.querySelector('.row-status');
		var decision = 'clear';
		if (btn.classList.contains('titlo-sdup-keep')) decision = 'keep';
		if (btn.classList.contains('titlo-sdup-noindex')) decision = 'noindex';
		if (btn.classList.contains('titlo-sdup-open')) decision = 'clear';
		statusEl.textContent = '…';
		post('section_dup_decision', {entity_id: id, decision: decision}).then(function (res) {
			statusEl.textContent = res.ok ? 'OK' : (res.error || 'Ошибка');
			statusEl.style.color = res.ok ? '#15803d' : '#c00';
			if (!res.ok) return;
			var hint = document.getElementById('titlo-sdup-hint');
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

	document.getElementById('titlo-sdup-reload').onclick = function () { page = 1; load(); };
	document.getElementById('titlo-sdup-probe-reset').onclick = function () {
		var b = this;
		b.disabled = true;
		post('section_dup_probe_reset', {}).then(function () {
			b.disabled = false;
			page = 1;
			load();
		});
	};
	document.getElementById('titlo-sdup-status').addEventListener('change', function () { page = 1; load(); });
	document.getElementById('titlo-sdup-kind').addEventListener('change', function () { page = 1; load(); });
	document.getElementById('titlo-sdup-h1').addEventListener('change', function () {
		post('section_dup_h1_source', {source: this.value}).then(function () { load({ keepHint: true }); });
	});
	document.getElementById('titlo-sdup-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { page = 1; load(); }
	});

	load();
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
