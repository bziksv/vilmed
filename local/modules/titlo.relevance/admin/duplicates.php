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
<?php AdminUi::renderDupBuildJs(); ?>

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
					Проверяется <strong>весь каталог</strong>. В каждом блоке — группа карточек-дублей.
					Первая строка (зелёный фон) — эталон: отмеченная «прорабатывать», иначе с наибольшим числом просмотров,
					иначе с более длинным описанием. У остальных — почему это дубль и чем отличаются слова.
				</li>
				<li>
					<strong>Типы:</strong>
					«одинаковые названия» — те же слова, бренд, модель и размер (порядок слов, знаки, «для» не важны);
					«отличие только в стране» — у одной карточки страна указана, у другой нет;
					«похожие названия» — слова совпадают на порог % и выше, разница показана («+пультом», «−Ворсма»).
					Разные бренды, модели, размеры, мощность, цвет, «детский/взрослый», «прямой/изогнутый», «с/без X» дублями не считаются.
				</li>
				<li>
					<strong>Линейки</strong> (фильтр «Тип») — одна модель в разных размерах/вариантах: это разные товары,
					массовых кнопок нет. Обычно их оставляют в индексе.
					<strong>Расширенный режим</strong> — линейки показываются вместе с дублями, и их можно закрыть массово:
					эталон — вариант с наибольшим числом просмотров, у остальных видно, сколько у них просмотров в % от эталона.
					Подходит для малопопулярных размеров/кодов, которые отдельно никто не ищет.
				</li>
				<li>
					«артикул другой» — названия совпадают, но артикулы разные: проверьте, не разные ли это товары
					(тогда лучше переименовать, чем закрывать).
				</li>
				<li>
					Карточки, которые не являются отдельной страницей (редирект в <code>.htaccess</code>, фактический 3xx или canonical на другой URL),
					в дубли не попадают. HTTP-проверка кешируется на 7 дней; «Перепроверить URL» — сбросить её и пересчитать группы.
				</li>
				<li>
					Группы считаются в фоне и сохраняются: страница открывается сразу из сохранённого результата.
					Решения и noindex всегда актуальные, а новые товары и переименования появятся после кнопки «Пересчитать».
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
				<option value="ignored" <?= $status === 'ignored' ? 'selected' : '' ?>>Кластеры: оставлены как есть</option>
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Кластеры: все</option>
				<option value="keep" <?= $status === 'keep' ? 'selected' : '' ?>>Отмеченные: прорабатывать</option>
				<option value="noindex" <?= $status === 'noindex' ? 'selected' : '' ?>>Отмеченные: noindex</option>
				<option value="decided" <?= $status === 'decided' ? 'selected' : '' ?>>Все с решением</option>
			</select>
		</label>
		<label>Тип:
			<select id="titlo-dup-kind">
				<option value="dups">дубли (все типы)</option>
				<option value="exact">одинаковые названия</option>
				<option value="similar">похожие названия</option>
				<option value="homonym">одно название — разные производители</option>
				<option value="lines">линейки (размеры/модели)</option>
				<option value="all">всё, включая линейки</option>
			</select>
		</label>
		<label title="Линейки (одна модель в разных размерах/мощностях/сериях) показываются вместе с дублями, и у них появляются кнопки «Эталон + закрыть остальных» / «Закрыть весь блок». Эталон линейки — вариант с наибольшим числом просмотров.">
			<input type="checkbox" id="titlo-dup-ext"> Расширенный режим (линейки)
		</label>
		<label title="Откуда брать H1 товара. По умолчанию — SEO-поле «Заголовок товара» инфоблока.">H1 из:
			<select id="titlo-dup-h1">
				<?php $h1Src = ProductDuplicates::h1Source(); ?>
				<?php foreach (ProductDuplicates::h1SourceOptions() as $k => $label): ?>
					<option value="<?= htmlspecialcharsbx($k) ?>" <?= $k === $h1Src ? 'selected' : '' ?>><?= htmlspecialcharsbx($label) ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<input type="text" id="titlo-dup-q" class="adm-input" placeholder="ID / название / код / артикул" value="<?= htmlspecialcharsbx($q) ?>" style="width:260px">
		<label>Порог %:
			<input type="number" id="titlo-dup-score" class="adm-input" value="<?= (int) ProductDuplicates::SIMILARITY_MIN ?>" min="50" max="99" style="width:70px">
		</label>
		<input type="button" id="titlo-dup-reload" class="adm-btn" value="Показать">
		<input type="button" id="titlo-dup-probe-reset" class="adm-btn" value="Перепроверить URL" title="Сбросить кеш HTTP-проверки (редиректы, canonical) и пересчитать группы">
		<span id="titlo-dup-list-status"></span>
	</div>

	<div id="titlo-dup-cache" class="titlo-cache-meta"></div>
	<div id="titlo-dup-build"></div>
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

	function actBtn(html, tip) {
		return '<span class="titlo-act">' + html +
			'<span class="titlo-act__tip">' + escapeHtml(tip) + '</span></span> ';
	}

	var extBox = document.getElementById('titlo-dup-ext');
	try { extBox.checked = localStorage.getItem('titlo_dup_ext') === '1'; } catch (e) {}
	function extMode() { return extBox.checked; }
	extBox.addEventListener('change', function () {
		try { localStorage.setItem('titlo_dup_ext', extBox.checked ? '1' : '0'); } catch (e) {}
		page = 1;
		load();
	});

	function probeText(p) {
		if (!p) return '';
		var s = '';
		if (p.pending) s += '; не успели проверить по HTTP ' + p.pending + ' URL — нажмите «Пересчитать»';
		if (p.blocked) s += '; антибот отдал заглушку на ' + p.blocked + ' URL — задайте cookie в настройках модуля';
		return s;
	}

	var builder = TitloDupBuild({
		entity: 'E',
		post: post,
		minScore: function () { return document.getElementById('titlo-dup-score').value; },
		mount: document.getElementById('titlo-dup-build'),
		stages: [
			{key: 'start', label: 'Запуск'},
			{key: 'fetch', label: 'Загрузка товаров'},
			{key: 'parse', label: 'Разбор названий'},
			{key: 'cluster', label: 'Поиск пар и групп'},
			{key: 'urls', label: 'URL и 301-редиректы'},
			{key: 'probe', label: 'Проверка страниц на сайте'},
			{key: 'cluster2', label: 'Пересборка без редиректов'},
			{key: 'enrich', label: 'H1, разделы, noindex'},
			{key: 'finish', label: 'Сортировка групп'},
			{key: 'save', label: 'Сохранение'}
		],
		onDone: function () { load({ keepHint: true }); }
	});

	function h1Line(item, isTop, refH1) {
		if (typeof item.h1 === 'undefined') return '';
		var h1 = String(item.h1 || '');
		if (h1 !== '' && h1 === item.name) {
			return '<div class="sdup-h1"><b>H1:</b> = названию' +
				(!isTop && refH1 && h1.toLowerCase() === String(refH1).toLowerCase() ? ' <span class="badge badge-distinct">H1 как у эталона</span>' : '') + '</div>';
		}
		var same = !isTop && h1 !== '' && refH1 && h1.toLowerCase() === String(refH1).toLowerCase();
		return '<div class="sdup-h1"><b>H1:</b> ' + (h1 !== '' ? escapeHtml(h1) : '<i>пусто</i>') +
			(same ? ' <span class="badge badge-distinct">H1 как у эталона</span>' : '') + '</div>';
	}

	function metaLine(item) {
		var bits = [];
		if (item.article) bits.push('артикул: ' + escapeHtml(item.article));
		if (item.manufacturer) bits.push('производитель: ' + escapeHtml(item.manufacturer));
		if (typeof item.views === 'number') bits.push('просмотров: ' + item.views);
		if (typeof item.text_len === 'number') bits.push(item.text_len > 0 ? ('описание: ' + item.text_len + ' симв.') : 'без описания');
		return bits.length ? '<div class="sdup-path">' + bits.join(' · ') + '</div>' : '';
	}

	function renderRow(item, isTop, refH1) {
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
			(item.path ? '<div class="sdup-path">' + escapeHtml(item.path) + '</div>' : '') +
			metaLine(item) +
			h1Line(item, isTop, refH1) +
			(item.sim_label ? '<div class="sim">' + escapeHtml(item.sim_label) + '</div>' : '') +
			(item.url_note ? '<div class="sim" style="color:#b45309">' + escapeHtml(item.url_note) + '</div>' : '') +
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
		var kind = document.getElementById('titlo-dup-kind').value;
		if (extMode() && kind === 'dups') kind = 'all';
		document.getElementById('titlo-dup-list-status').textContent = 'Загрузка…';
		post('list_duplicates', {status: status, kind: kind, q: q, page: page, page_size: 15, min_score: minScore}).then(function (res) {
			var body = document.getElementById('titlo-dup-body');
			var hint = document.getElementById('titlo-dup-hint');
			if (!res.ok) {
				body.textContent = res.error || 'Ошибка';
				return;
			}
			if (res.need_build) {
				document.getElementById('titlo-dup-list-status').textContent = '';
				document.getElementById('titlo-dup-cache').innerHTML = '';
				body.innerHTML = '<p>Группы для порога ' + escapeHtml(minScore) + '% ещё не посчитаны — считаю, список появится сам.</p>';
				document.getElementById('titlo-dup-pager').innerHTML = '';
				var bp = res.build || {};
				if (bp.state === 'running') builder.watch(bp); else builder.start(false);
				return;
			}
			builder.meta(res.cache, document.getElementById('titlo-dup-cache'));

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

			var bk = res.open_by_kind || {};
			document.getElementById('titlo-dup-list-status').textContent = 'Групп: ' + res.total +
				' (открытых: одинаковых ' + (bk.exact || 0) + ', похожих ' + (bk.similar || 0) +
				', разных производителей ' + (bk.homonym || 0) + ', линеек ' + (bk.lines || 0) + ')' +
				(res.checked_products ? '; проверено товаров: ' + res.checked_products : '') +
				(res.redirect_hidden ? '; скрыто — редирект/canonical на другую страницу: ' + res.redirect_hidden : '') +
				probeText(res.probe);
			if (!res.clusters || !res.clusters.length) {
				body.innerHTML = '<p>Ничего не найдено. Проверьте ID, поиск по названию, фильтр «Отмеченные: прорабатывать» или понизьте порог %.</p>';
				document.getElementById('titlo-dup-pager').innerHTML = '';
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
				var ignoreBtn = '';
				if (cl.size >= 2 && cl.status === 'ignored') {
					ignoreBtn = '<span class="titlo-act"><input type="button" class="adm-btn titlo-dup-unignore" value="Вернуть в работу">' +
						'<span class="titlo-act__tip">Снова показывать группу в открытых.</span></span>';
				} else if (cl.size >= 2 && cl.status === 'open') {
					ignoreBtn = '<span class="titlo-act"><input type="button" class="adm-btn titlo-dup-ignore" value="Оставить как есть">' +
						'<span class="titlo-act__tip">Ничего не менять и скрыть группу из открытых. Найти и вернуть — фильтр «Кластеры: оставлены как есть». Если в группу добавится новая карточка, она снова станет открытой.</span></span>';
				}
				var bulkBtn = (cl.size >= 2 && cl.kind !== 'homonym' && (cl.kind !== 'lines' || extMode()))
					? ('<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-dup-cluster-noindex" value="Эталон + закрыть остальных">' +
						'<span class="titlo-act__tip">Первую карточку (зелёная) — «прорабатывать», все остальные в группе — закрыть от индексации.</span>' +
						'</span>' +
						'<span class="titlo-act">' +
						'<input type="button" class="adm-btn titlo-dup-cluster-noindex-all" value="Закрыть весь блок">' +
						'<span class="titlo-act__tip">Закрыть от индексации ВСЕ карточки группы, включая эталон (зелёную строку).</span>' +
						'</span>')
					: '';
				var isLine = cl.kind === 'lines';
				var refH1 = cl.items.length ? cl.items[0].h1 : '';
				var rows = cl.items.map(function (item, i) {
					return renderRow(item, i === 0 && (!isLine || extMode()), refH1);
				}).join('');
				return '<div class="cluster" data-kind="' + escapeHtml(cl.kind || '') + '" data-key="' + escapeHtml(cl.key) + '" data-keep-id="' + keepId + '" data-ids="' + escapeHtml(ids.join(',')) + '">' +
					'<div class="cluster-h">' +
					'<span>Группа ' + (idx + 1 + (res.page - 1) * res.page_size) + '</span>' +
					(cl.kind_label ? '<span class="badge badge-kind">' + escapeHtml(cl.kind_label) + '</span>' : '') +
					(cl.size >= 2 && cl.score < 100 ? '<span class="score">совпадение ≥ ' + cl.score + '%</span>' : '') +
					'<span>' + cl.size + ' карточ' + (cl.size >= 5 ? 'ек' : 'ки') + '</span>' +
					'<span class="' + stClass + '">' + (isLine && cl.status === 'open' ? 'разные товары' : stLabel) + '</span>' +
					bulkBtn + ignoreBtn +
					'</div>' +
					'<table><thead><tr><th style="width:80px">ID</th><th>Название / насколько похож на эталон</th><th style="width:400px">Решение</th></tr></thead><tbody>' +
					rows + '</tbody></table></div>';
			}).join('');
			renderPager(res);
		});
	}

	document.getElementById('titlo-dup-body').addEventListener('click', function (e) {
		var btn = e.target;
		if (btn.classList.contains('titlo-dup-ignore') || btn.classList.contains('titlo-dup-unignore')) {
			var cl = btn.closest('.cluster');
			if (!cl) return;
			var unignore = btn.classList.contains('titlo-dup-unignore');
			btn.disabled = true;
			post(unignore ? 'dup_unignore' : 'dup_ignore', {entity: 'E', ids: cl.getAttribute('data-ids') || ''}).then(function (res) {
				btn.disabled = false;
				var hint = document.getElementById('titlo-dup-hint');
				hint.style.display = '';
				hint.innerHTML = res.ok
					? (unignore ? 'Группа возвращена в открытые.' : 'Группа оставлена как есть и скрыта. Вернуть — фильтр «Кластеры: оставлены как есть».')
					: '<span style="color:#c00">' + escapeHtml(res.error || 'Ошибка') + '</span>';
				if (res.ok) load({ keepHint: true });
			});
			return;
		}
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
			if (cluster.getAttribute('data-kind') === 'lines') {
				confirmMsg = 'Это линейка — разные товары (размеры/модели), не копии.\n' + confirmMsg;
			}
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
	document.getElementById('titlo-dup-probe-reset').onclick = function () {
		if (!confirm('Сбросить HTTP-проверку всех URL и пересчитать группы? Займёт несколько минут.')) return;
		builder.start(true);
	};
	document.getElementById('titlo-dup-status').addEventListener('change', function () { page = 1; load(); });
	document.getElementById('titlo-dup-kind').addEventListener('change', function () { page = 1; load(); });
	document.getElementById('titlo-dup-h1').addEventListener('change', function () {
		post('dup_h1_source', {source: this.value}).then(function () { load({ keepHint: true }); });
	});
	document.getElementById('titlo-dup-q').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { page = 1; load(); }
	});

	load();
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
