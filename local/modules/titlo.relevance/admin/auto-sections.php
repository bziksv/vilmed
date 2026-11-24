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

$APPLICATION->SetTitle('Titlo: автоматическая проработка категорий');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$ajaxUrl = '/bitrix/admin/titlo_relevance_ajax.php?lang=' . LANGUAGE_ID;
$hasKey = Config::apiKey() !== '';
$filter = (string) ($_GET['filter'] ?? 'todo');
$q = (string) ($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$sectionIds = \Titlo\Relevance\CatalogRepository::normalizeSectionIds(
	$_GET['section_ids'] ?? ($_GET['section_id'] ?? [])
);

$policy = Config::getAutoOption('existing_policy', 'S', BatchQueue::POLICY_OVERWRITE);
if ($policy !== BatchQueue::POLICY_SKIP) {
	$policy = BatchQueue::POLICY_OVERWRITE;
}
$tlpMissing = (int) Config::getAutoOption('tlp_missing', 'S', '300');
$tlpDiff = (int) Config::getAutoOption('tlp_diff', 'S', '5');
$runMode = Config::getAutoOption('run_mode', 'S', BatchQueue::MODE_FULL);
$runMode = BatchQueue::normalizeRunMode($runMode);
$sort = (string) ($_GET['sort'] ?? 'score_asc');
if ($sort !== 'id_desc') {
	$sort = 'score_asc';
}
$analysisEngine = Config::getAutoOption('analysis_engine', 'S', Config::analysisEngineDefault());
if (!in_array($analysisEngine, ['yandex', 'google'], true)) {
	$analysisEngine = 'yandex';
}
$analysisRegion = Config::getAutoOption(
	'analysis_region_' . $analysisEngine,
	'S',
	Config::analysisRegionDefault($analysisEngine)
);
$analysisTop = (int) Config::getAutoOption('analysis_top', 'S', (string) Config::analysisTopDefault());
if (!in_array($analysisTop, Config::analysisTopOptions(), true)) {
	$analysisTop = Config::analysisTopDefault();
}
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-names titlo-auto-products titlo-auto-sections">
	<?php if (!$hasKey): ?>
		<div class="adm-info-message-wrap adm-info-message-red">
			<div class="adm-info-message">
				Укажите API-ключ в
				<a href="/bitrix/admin/settings.php?lang=<?= LANGUAGE_ID ?>&mid=titlo.relevance">настройках модуля</a>.
			</div>
		</div>
	<?php endif; ?>

	<details class="titlo-howto">
		<summary>Как работает автоматическая проработка категорий</summary>
		<div class="titlo-howto__body">
			<p><strong>Что делает эта страница.</strong> То же, что автомат для товаров, только для <b>разделов каталога</b>:
				проверяет страницу раздела, пишет для неё текст с нужными словами, сохраняет и проверяет ещё раз. Короткого описания (анонса) у разделов нет.</p>
			<p><b>Перед запуском:</b> у раздела должна быть короткая фраза (страница «Проверка названий категорий»).
				И сначала попробуйте 1–2 раздела вручную на странице «Проработка».</p>

			<p><strong>Как запустить — по шагам</strong></p>
			<ol>
				<li><b>Зелёный блок «Отбор»</b> — выберите, с чем работать. Нажмите «Ветка каталога», найдите раздел или бренд, отметьте и нажмите «Применить».
					Лучше начинать с одного раздела, а не со всего каталога.</li>
				<li>В «Фильтре» выберите, какие разделы показать (подсказки ниже).</li>
				<li>Поставьте галочки у нужных разделов в таблице (или «выбрать все»).</li>
				<li><b>Синий блок «Параметры»</b> — выберите режим (обычно «Полная проработка») и что делать, если описание уже есть.</li>
				<li>Нажмите <b>«В очередь»</b>. Всё, дальше программа работает сама, страницу можно закрыть.</li>
			</ol>

			<p><strong>Какой режим выбрать</strong></p>
			<ul>
				<li><b>Только анализ</b> — просто узнать баллы, ничего не меняя на сайте. Удобно, чтобы найти самые слабые страницы.</li>
				<li><b>Полная проработка</b> — проверить страницу, написать описание, сохранить его на сайт и проверить ещё раз, стало ли лучше.</li>
				<li><b>Повторная доработка</b> — улучшить описание, которое уже писали раньше.</li>
			</ul>

			<p><strong>Если описание уже есть</strong></p>
			<ul>
				<li><b>«Переписать описание заново»</b> — старый текст заменится новым.</li>
				<li><b>«Не трогать текст, только отметить»</b> — текст останется, раздел просто отметится как пройденный. Подходит для описаний, написанных вручную.</li>
			</ul>

			<p><strong>Что в таблице</strong></p>
			<ul>
				<li><b>Описание</b> — «пусто» или «есть · N» (сколько в нём символов).</li>
				<li><b>Балл</b> — насколько страница нравится поисковику по последней проверке: ваш балл / сколько нужно. Глазик открывает подробности.</li>
				<li><b>Автопроработка</b> — дата, когда автомат закончил с этой страницей. Пусто — автомат её ещё не трогал (даже если описание есть).</li>
			</ul>

			<p><strong>Журнал внизу</strong></p>
			<ul>
				<li>Показывает, что в очереди, что делается, что готово и где ошибка.</li>
				<li><b>«Текст не писали»</b> — страницу проверили, но описание не меняли (так было задано).</li>
				<li>Ошибки «HTTP 500» или «завис в кабинете» чаще всего значат, что сервис Titlo был перегружен.
					Программа сама повторяет запросы, ждёт до часа и подхватывает задания, которые сервис доделал позже.
					Если ошибка осталась — отметьте строки и перезапустите.</li>
			</ul>
			<details class="titlo-howto__tech">
				<summary>Для специалиста</summary>
			<ul>
				<li>
					В списке — активные категории с короткой фразой
					(шаг «Проверка названий категорий»). Без фразы в автомат не берём.
				</li>
				<li>
					Два блока: <strong>Отбор категорий</strong> и
					<strong>Параметры проработки</strong>.
				</li>
				<li>
					<strong>Отбор</strong>:
					ветка каталога (поиск по частям слов, «Выбрать все», «Применить»),
					фильтр/сортировка, поиск по ID/названию внутри выборки.
					Пустой выбор ветки = весь каталог.
				</li>
				<li>
					<strong>Параметры проработки</strong> — ПС / регион / ТОП, режим,
					промпт описания категории, политика «если описание уже есть», TLP, «В очередь».
					Анонса у категорий нет.
				</li>
				<li>
					Режимы: <em>только анализ</em>, <em>полная проработка</em>,
					<em>повторная доработка</em> — как у товаров.
				</li>
				<li>
					<strong>Описание</strong> — текст раздела (DESCRIPTION).
					Колонка «Балл» + глазик — сводка без перехода на проработку.
				</li>
				<li>
					<strong>Верхняя таблица</strong> — категории. <strong>Внизу</strong> — журнал только по категориям.
					«Автопроработка» — автомат закрыл раздел, а не «есть ли текст».
				</li>
				<li>
					Сценарий: ветка «Ветеринария» → фильтр «Уже есть балл» → отметили слабые →
					полная проработка → «Переписать» → в очередь.
				</li>
				<li>
					Очередь крутит агент; вкладка ускоряет опрос статуса.
					В модель — до 80 слов TLP.
				</li>
			</ul>
			</details>
		</div>
	</details>

	<section class="titlo-panel titlo-panel--select" aria-labelledby="titlo-panel-select-title">
		<header class="titlo-panel__head">
			<h3 class="titlo-panel__title" id="titlo-panel-select-title">Отбор категорий</h3>
			<span class="titlo-panel__hint">Сначала ветка и фильтр, затем поиск внутри выборки</span>
		</header>
		<div class="titlo-panel__body">
			<div class="filters">
				<?php AdminUi::renderSectionBranchFilter($sectionIds, 'категории'); ?>
				<div class="titlo-list-scope">
					<div class="titlo-list-scope__head">
						<span class="titlo-list-scope__title">Список</span>
					</div>
					<div class="titlo-list-scope__row">
						<label>Фильтр
							<span class="titlo-help" tabindex="0" aria-label="Фильтр списка">?
								<span class="titlo-help__tip">
									Все варианты — только категории с короткой фразой (без неё в автомат не берём).<br>
									<b>Без описания (ещё без авто)</b> — нет даты автопроработки и нет текста в DESCRIPTION: нужна генерация.<br>
									<b>Есть описание (без отметки авто)</b> — текст раздела уже есть (часто после ручной проработки), даты автомата нет. Удобно отметить политикой «не трогать текст».<br>
									<b>Все без отметки авто</b> — оба случая вместе (старое «Ещё без автопроработки»).<br>
									<b>Уже есть балл анализа</b> — был анализ, цифры в «Балл» есть; дата автопроработки при этом может быть пустой.<br>
									<b>Прошли автопроработку</b> — стоит дата: либо сгенерировали текст, либо отметили без генерации (политика «описание уже есть»). Это не «есть текст в разделе», а именно отметка автомата.<br>
									<b>Все с короткой фразой</b> — весь пул, из которого можно ставить в очередь.<br>
									<b>Одинаковая фраза у нескольких категорий</b> — например «Мониторы пациента, Россия» и «…, Китай» с одной фразой. Такие категории в любом фильтре идут первыми, группой: сверху шапка с общим родителем и кнопкой «Исключить дочерние».<br>
									<b>Исключённые из автопроработки</b> — категории с отметкой «исключить» (поле «Titlo: не прорабатывать автоматически»). Из остальных рабочих фильтров и групп они пропадают; вернуть — кнопкой «вернуть».<br>
									<b>Исключённые, но открыты для индексации</b> — исключили, а страницу noindex не закрыли; там есть кнопка «Закрыть noindex все найденные».<br>
									<b>Закрыты от индексации (noindex)</b> — все категории с noindex на странице (исключённые и нет).
								</span>
							</span>:
							<select id="titlo-auto-filter">
								<option value="todo" <?= $filter === 'todo' ? 'selected' : '' ?>>Без описания (ещё без авто)</option>
								<option value="todo_ready" <?= $filter === 'todo_ready' ? 'selected' : '' ?>>Есть описание (без отметки авто)</option>
								<option value="todo_all" <?= $filter === 'todo_all' ? 'selected' : '' ?>>Все без отметки авто</option>
								<option value="scored" <?= $filter === 'scored' ? 'selected' : '' ?>>Уже есть балл анализа</option>
								<option value="done" <?= $filter === 'done' ? 'selected' : '' ?>>Прошли автопроработку</option>
								<option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Все с короткой фразой</option>
								<option value="phrase_dup" <?= $filter === 'phrase_dup' ? 'selected' : '' ?>>Одинаковая фраза у нескольких категорий</option>
								<option value="skipped" <?= $filter === 'skipped' ? 'selected' : '' ?>>Исключённые из автопроработки</option>
								<option value="skipped_open" <?= $filter === 'skipped_open' ? 'selected' : '' ?>>Исключённые, но открыты для индексации</option>
								<option value="noindex" <?= $filter === 'noindex' ? 'selected' : '' ?>>Закрыты от индексации (noindex)</option>
							</select>
						</label>
						<label>Сортировка
							<span class="titlo-help" tabindex="0" aria-label="Сортировка">?
								<span class="titlo-help__tip"><b>Балл ↑</b> — сначала самые слабые по последнему анализу (удобно выбирать, кого прогонять дальше).<br><b>ID ↓</b> — свежие категории сверху.</span>
							</span>:
							<select id="titlo-auto-sort">
								<option value="score_asc" <?= $sort === 'score_asc' ? 'selected' : '' ?>>Балл ↑ (низкий сверху)</option>
								<option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>ID ↓</option>
							</select>
						</label>
						<input type="search" id="titlo-auto-q" class="adm-input" placeholder="ID / название категории"
							value="<?= htmlspecialcharsbx($q) ?>" style="width:200px" autocomplete="off" spellcheck="false"
							title="Быстрый поиск по ID или частям названия">
						<input type="button" id="titlo-auto-reload" class="adm-btn" value="Показать">
						<span id="titlo-auto-list-status" class="status"></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="titlo-panel titlo-panel--run" aria-labelledby="titlo-panel-run-title">
		<header class="titlo-panel__head">
			<h3 class="titlo-panel__title" id="titlo-panel-run-title">Параметры проработки</h3>
			<span class="titlo-panel__hint">ПС, режим, промпт и постановка выбранных категорий в очередь</span>
		</header>
		<div class="titlo-panel__body bulk-bar">
		<div class="titlo-auto-analysis" style="flex:1 1 100%">
		<label>Поиск / регион / топ
			<span class="titlo-help" tabindex="0" aria-label="Параметры выдачи">?
				<span class="titlo-help__tip">Те же поля, что у товаров и на проработке одной категории. Яндекс и Google — разные номера городов. Очередь берёт то, что выбрано здесь.</span>
			</span>
		</label>
		<label style="margin-right:12px">ПС
			<select id="titlo-auto-engine" class="adm-input" style="width:120px">
				<option value="yandex" <?= $analysisEngine === 'yandex' ? 'selected' : '' ?>>Яндекс</option>
				<option value="google" <?= $analysisEngine === 'google' ? 'selected' : '' ?>>Google</option>
			</select>
		</label>
		<label style="margin-right:12px">Регион
			<span class="titlo-region-ac" id="titlo-auto-region-ac">
				<input type="hidden" id="titlo-auto-region" value="<?= htmlspecialcharsbx($analysisRegion) ?>">
				<input type="text" id="titlo-auto-region-q" class="adm-input" autocomplete="off"
					placeholder="Начните вводить город"
					value="<?= htmlspecialcharsbx(Config::analysisRegionLabel($analysisRegion, $analysisEngine)) ?>">
				<div id="titlo-auto-region-list" class="titlo-region-ac__list"></div>
			</span>
		</label>
		<label>ТОП
			<select id="titlo-auto-top" class="adm-input" style="width:80px">
				<?php foreach (Config::analysisTopOptions() as $topOpt): ?>
					<option value="<?= (int) $topOpt ?>"<?= (int) $topOpt === $analysisTop ? ' selected' : '' ?>><?= (int) $topOpt ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		</div>
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
		<label><span id="titlo-auto-prompt-detail-label">Промпт описания категории</span>
			<span class="titlo-help" tabindex="0" aria-label="Промпт описания категории">?
				<span class="titlo-help__tip">Список зависит от режима: полная проработка / повторная доработка. Привязка режима — в «Промпты». К тексту дописываются TLP и HTML страницы категории.</span>
			</span>:
			<select id="titlo-auto-prompt-detail" class="adm-input titlo-prompt-select" data-type="category" style="min-width:320px"></select>
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
	</section>

	<div id="titlo-auto-noindex-bar" class="titlo-auto-noindex-bar" hidden>
		<span id="titlo-auto-noindex-msg"></span>
		<input type="button" id="titlo-auto-noindex-all" class="adm-btn" value="Закрыть noindex все найденные">
	</div>

	<div id="titlo-auto-sel-bar" class="titlo-auto-sel-bar" hidden>
		<span id="titlo-auto-sel-msg"></span>
		<input type="button" id="titlo-auto-sel-all-match" class="adm-btn" value="Выбрать все найденные">
		<input type="button" id="titlo-auto-sel-clear" class="adm-btn" value="Снять выбор">
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
				<span class="titlo-help" tabindex="0" aria-label="Описание категории">?
					<span class="titlo-help__tip">Текст раздела категории (DESCRIPTION). «есть · N» — уже заполнено, N символов без HTML. «пусто» — генерируем с нуля.</span>
				</span>
			</th>
			<th style="width:120px">Балл
				<span class="titlo-help" tabindex="0" aria-label="Балл анализа">?
					<span class="titlo-help__tip">Последний анализ: ваш балл / рекомендуемый. Глазик — сводка, облака TF-IDF и TLP.</span>
				</span>
			</th>
			<th style="width:140px">Автопроработка
				<span class="titlo-help" tabindex="0" aria-label="Автопроработка">?
					<span class="titlo-help__tip">Дата, когда автомат <b>закрыл</b> категорию: сгенерировал описание или отметил без генерации (политика «описание уже есть»). Пусто ≠ «нет текста» — текст может быть, а отметки ещё нет. Режим «Только анализ» сюда дату не пишет.</span>
				</span>
			</th>
			<th style="width:130px">Очередь
				<span class="titlo-help" tabindex="0" aria-label="Статус очереди">?
					<span class="titlo-help__tip">Текущий шаг задания: анализ, генерация, сохранение и т.д. «—» — не в очереди.</span>
				</span>
			</th>
			<th style="width:190px">Действия
				<span class="titlo-help" tabindex="0" aria-label="Действия">?
					<span class="titlo-help__tip"><b>Проработка</b> — точечная страница анализа и генерации по одной категории.<br><b>Сайт</b> — публичный раздел в новой вкладке.<br><b>Исключить</b> — убрать из автопроработки (вернуть — фильтр «Исключённые»).<br><b>+ noindex</b> — исключить и закрыть страницу категории от индексации; товары не закрываются.</span>
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
				Журнал заданий. Отметьте нужные строки:
				«Запустить выбранные» — снова поставить в работу (режим из панели выше);
				«Убрать из очереди» — снять задания со статусом «в очереди» или «ошибка» (без перезапуска).
				Кнопки выбора отмечают строки на текущей странице журнала; отмеченные сохраняются при перелистывании.
			</p>
			<div class="filters" id="titlo-auto-queue-filters" style="margin:10px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
				<label>Статус:
					<select id="titlo-q-status" class="adm-input">
						<option value="">Все</option>
						<option value="queued">в очереди</option>
						<option value="running">выполняются</option>
						<option value="done">готово</option>
						<option value="failed">ошибки</option>
						<option value="skipped">текст не писали</option>
						<option value="cancelled">снято</option>
					</select>
				</label>
				<label>Режим:
					<select id="titlo-q-run-mode" class="adm-input">
						<option value="">Все</option>
						<option value="full">полная</option>
						<option value="analyze_only">только анализ</option>
						<option value="refine">доработка</option>
					</select>
				</label>
				<label>Система:
					<select id="titlo-q-engine" class="adm-input">
						<option value="">Все</option>
						<option value="yandex">Яндекс</option>
						<option value="google">Google</option>
						<option value="none">не указано</option>
					</select>
				</label>
				<label>ТОП:
					<select id="titlo-q-top" class="adm-input">
						<option value="">Все</option>
						<option value="10">ТОП-10</option>
						<option value="20">ТОП-20</option>
						<option value="30">ТОП-30</option>
						<option value="50">ТОП-50</option>
					</select>
				</label>
				<input type="search" id="titlo-q-q" class="adm-input" placeholder="№ / категория / фраза / анализ" style="width:220px">
				<input type="button" id="titlo-q-apply" class="adm-btn-save" value="Показать">
				<input type="button" id="titlo-q-clear" class="adm-btn" value="Сбросить">
			</div>
			<div style="margin:8px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
				<div id="titlo-auto-queue-counts" class="bulk-status"></div>
				<input type="button" id="titlo-auto-requeue-selected" class="adm-btn-save" value="Запустить выбранные (0)" <?= $hasKey ? '' : 'disabled' ?>>
				<input type="button" id="titlo-auto-cancel-selected" class="adm-btn" value="Убрать из очереди (0)" disabled>
				<input type="button" id="titlo-auto-queue-select-queued" class="adm-btn" value="Выбрать «в очереди»">
				<input type="button" id="titlo-auto-queue-select-failed" class="adm-btn" value="Выбрать ошибки">
				<input type="button" id="titlo-auto-queue-select-no-text" class="adm-btn" value="Выбрать «текст не писали»">
				<span id="titlo-auto-requeue-status" class="bulk-status"></span>
			</div>
			<div class="titlo-queue-scroll"><table class="titlo-queue-table">
				<thead>
				<tr>
					<th style="width:36px"><input type="checkbox" id="titlo-auto-queue-check-all" aria-label="Выбрать все в журнале"></th>
					<th style="width:50px">№</th>
					<th style="width:150px">Категория</th>
					<th class="titlo-queue-table__phrase">Фраза</th>
					<th style="width:90px">Режим</th>
					<th style="width:110px">Система
						<span class="titlo-help" tabindex="0" aria-label="Поисковая система">?
							<span class="titlo-help__tip">Поисковик и регион, с которыми задание ушло в Titlo.</span>
						</span>
					</th>
					<th style="width:70px">ТОП</th>
					<th style="width:220px">Итог
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
					<th class="titlo-queue-table__err">Ошибка</th>
				</tr>
				</thead>
				<tbody id="titlo-auto-queue-body">
				<tr><td colspan="11">Пока пусто</td></tr>
				</tbody>
			</table></div>
			<div class="pager" id="titlo-auto-queue-pager"></div>
		</div>
	</details>
</div>

<script>
(function () {
	var ajaxUrl = <?= json_encode($ajaxUrl) ?>;
	var sessid = <?= json_encode(bitrix_sessid()) ?>;
	var page = <?= (int) $page ?>;
	var hasKey = <?= $hasKey ? 'true' : 'false' ?>;
	var entityType = 'S';
	var selected = {};
	var queueSelected = {};
	var queuePage = 1;
	var QUEUE_PAGE_SIZE = 50;
	var QUEUE_COLSPAN = 11;
	var lastBatchKey = '';
	var pollTimer = null;
	var expandCache = {};
	var COLSPAN = 9;
	var listTotal = 0;
	var selectAllMatching = false;

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

	var regionsByEngine = {
		yandex: <?= json_encode(Config::analysisYandexRegionsList(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>,
		google: <?= json_encode(Config::analysisGoogleRegionsList(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>
	};
	var popularByEngine = {
		yandex: <?= json_encode(array_keys(Config::analysisRegions()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
		google: <?= json_encode(array_keys(Config::analysisGoogleRegions()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
	};
	var defaultRegionByEngine = {
		yandex: <?= json_encode(Config::analysisRegionDefault('yandex'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
		google: <?= json_encode(Config::analysisRegionDefault('google'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
	};
	var savedRegionByEngine = {yandex: '', google: ''};

	function currentEngine() {
		var el = document.getElementById('titlo-auto-engine');
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
			if (String(list[i].id) === id) return (list[i].name || '') + ' [' + id + ']';
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
			if ((name + ' ' + id).toLowerCase().indexOf(q) === -1) continue;
			out.push({id: id, text: name + ' [' + id + ']'});
			if (out.length >= 25) break;
		}
		return out;
	}
	function closeRegionList() {
		var wrap = document.getElementById('titlo-auto-region-ac');
		if (wrap) wrap.classList.remove('is-open');
	}
	function setRegion(id, text) {
		var hid = document.getElementById('titlo-auto-region');
		var inp = document.getElementById('titlo-auto-region-q');
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
	function renderRegionList(items) {
		var box = document.getElementById('titlo-auto-region-list');
		var wrap = document.getElementById('titlo-auto-region-ac');
		if (!box || !wrap) return;
		box.innerHTML = items.length
			? items.map(function (it) {
				return '<div data-id="' + escapeHtml(String(it.id)) + '">' + escapeHtml(String(it.text || '')) + '</div>';
			}).join('')
			: '<div class="empty">Ничего не найдено — введите город, например «Казань»</div>';
		wrap.classList.add('is-open');
		Array.prototype.forEach.call(box.querySelectorAll('div[data-id]'), function (el) {
			el.onclick = function () { setRegion(el.getAttribute('data-id'), el.textContent); };
		});
	}
	function analysisFields() {
		return {
			engine: (document.getElementById('titlo-auto-engine') || {}).value || 'yandex',
			region: (document.getElementById('titlo-auto-region') || {}).value || '',
			top: (document.getElementById('titlo-auto-top') || {}).value || ''
		};
	}
	(function initRegionSearch() {
		var inp = document.getElementById('titlo-auto-region-q');
		var eng = document.getElementById('titlo-auto-engine');
		if (!inp) return;
		inp.addEventListener('focus', function () {
			var curId = document.getElementById('titlo-auto-region').value;
			var curLabel = regionLabelById(curId);
			renderRegionList(filterRegions(inp.value === curLabel ? '' : inp.value));
		});
		inp.addEventListener('input', function () { renderRegionList(filterRegions(inp.value)); });
		inp.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape') closeRegionList();
			if (ev.key === 'Enter') {
				ev.preventDefault();
				var first = document.querySelector('#titlo-auto-region-list div[data-id]');
				if (first) setRegion(first.getAttribute('data-id'), first.textContent);
			}
		});
		if (eng) eng.addEventListener('change', applyEngineRegion);
		document.addEventListener('click', function (ev) {
			var wrap = document.getElementById('titlo-auto-region-ac');
			if (wrap && !wrap.contains(ev.target)) closeRegionList();
		});
		try {
			var saved = JSON.parse(localStorage.getItem('titlo_analysis_params_v1') || '{}') || {};
			if (saved.engine && eng) eng.value = saved.engine;
			if (saved.top && document.getElementById('titlo-auto-top')) {
				document.getElementById('titlo-auto-top').value = String(saved.top);
			}
			if (saved.regionsByEngine) {
				savedRegionByEngine.yandex = saved.regionsByEngine.yandex || '';
				savedRegionByEngine.google = saved.regionsByEngine.google || '';
			} else if (saved.region) {
				savedRegionByEngine[(saved.engine === 'google') ? 'google' : 'yandex'] = String(saved.region);
			}
			if (saved.engine || saved.region) applyEngineRegion();
		} catch (e) {}
	})();

	function safeHref(u) {
		u = String(u || '').trim();
		if (!u) return '#';
		if (u.indexOf('//') === 0) return '#';
		if (u.charAt(0) === '/') return escapeHtml(u);
		if (/^https?:\/\//i.test(u)) return escapeHtml(u);
		return '#';
	}

	function currentSectionIds() {
		if (window.TitloSectionBranch && typeof TitloSectionBranch.getIds === 'function') {
			return TitloSectionBranch.getIds();
		}
		return [];
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

	function listFilterParams() {
		return {
			q: document.getElementById('titlo-auto-q').value,
			filter: document.getElementById('titlo-auto-filter').value,
			sort: document.getElementById('titlo-auto-sort').value,
			section_ids: currentSectionIds().join(',')
		};
	}

	function updateSelBar() {
		var bar = document.getElementById('titlo-auto-sel-bar');
		var msg = document.getElementById('titlo-auto-sel-msg');
		var btnAll = document.getElementById('titlo-auto-sel-all-match');
		if (!bar || !msg || !btnAll) return;
		var n = selectedCount();
		var pageN = document.querySelectorAll('.titlo-auto-row').length;
		if (n < 1) {
			bar.hidden = true;
			return;
		}
		bar.hidden = false;
		if (selectAllMatching || (listTotal > 0 && n >= listTotal)) {
			msg.textContent = n < listTotal
				? ('Выбрано ' + n + ' из ' + listTotal + ' (лимит за раз)')
				: ('Выбрано все найденные: ' + n);
			btnAll.style.display = 'none';
		} else if (listTotal > pageN) {
			msg.textContent = 'Выбрано на странице: ' + n + '.';
			btnAll.style.display = '';
			btnAll.value = 'Выбрать все найденные (' + listTotal + ')';
		} else {
			msg.textContent = 'Выбрано: ' + n;
			btnAll.style.display = 'none';
		}
	}

	function clearSelection() {
		selected = {};
		selectAllMatching = false;
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-row'), function (cb) {
			cb.checked = false;
		});
		var all = document.getElementById('titlo-auto-check-all');
		if (all) all.checked = false;
		updateEnqueueBtn();
	}

	function updateEnqueueBtn() {
		var btn = document.getElementById('titlo-auto-enqueue');
		if (!btn) return;
		var n = selectedCount();
		btn.value = 'В очередь (' + n + ')';
		btn.disabled = !hasKey || n < 1;
		updateSelBar();
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
			lab.textContent = mode === 'refine' ? 'Промпт доработки категории' : 'Промпт описания категории';
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
			['category'].forEach(function (type) {
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
					opt.textContent = it.name + (it.is_default ? ' ★' : '') + ' — ' + (it.last_used_label || 'не использовался');
					if (pick && String(it.id) === String(pick)) opt.selected = true;
					sel.appendChild(opt);
				});
				if (!sel.value && sel.options.length) sel.selectedIndex = 0;
			});
			syncModeUi();
		});
	}

	function pagerHtml(cur, pages, total, extra) {
		function btn(p, label, cls, title) {
			var dis = p < 1 || p > pages || p === cur;
			return '<button type="button" class="titlo-pg__btn' + (cls ? ' ' + cls : '') + '"' +
				(dis ? ' disabled' : ' data-goto="' + p + '"') + (title ? ' title="' + title + '"' : '') + '>' + label + '</button>';
		}
		var nums = [];
		var from = Math.max(1, cur - 2);
		var to = Math.min(pages, cur + 2);
		if (from > 1) {
			nums.push(btn(1, '1'));
			if (from > 2) nums.push('<span class="titlo-pg__gap">…</span>');
		}
		for (var i = from; i <= to; i++) {
			nums.push(i === cur ? '<span class="titlo-pg__cur">' + i + '</span>' : btn(i, String(i)));
		}
		if (to < pages) {
			if (to < pages - 1) nums.push('<span class="titlo-pg__gap">…</span>');
			nums.push(btn(pages, String(pages)));
		}
		return '<div class="titlo-pg">' +
			btn(cur - 1, '← Назад', 'titlo-pg__btn--nav') +
			nums.join('') +
			btn(cur + 1, 'Вперёд →', 'titlo-pg__btn--nav') +
			'<span class="titlo-pg__jump">стр. <input type="number" class="titlo-pg__input" min="1" max="' + pages + '" value="' + cur + '"> из ' + pages + '</span>' +
			'<span class="titlo-pg__total">всего ' + total + (extra ? ', ' + extra : '') + '</span>' +
			'</div>';
	}

	function bindPager(host, go) {
		Array.prototype.forEach.call(host.querySelectorAll('[data-goto]'), function (b) {
			b.onclick = function (e) {
				e.preventDefault();
				go(parseInt(b.getAttribute('data-goto'), 10) || 1);
			};
		});
		var inp = host.querySelector('.titlo-pg__input');
		if (inp) {
			inp.onkeydown = function (e) {
				if (e.key !== 'Enter') return;
				e.preventDefault();
				var max = parseInt(inp.getAttribute('max'), 10) || 1;
				go(Math.max(1, Math.min(max, parseInt(inp.value, 10) || 1)));
			};
			inp.onchange = function () {
				var max = parseInt(inp.getAttribute('max'), 10) || 1;
				go(Math.max(1, Math.min(max, parseInt(inp.value, 10) || 1)));
			};
		}
	}

	function renderPager(total, pageSize, pages) {
		var host = document.getElementById('titlo-auto-pager');
		if (!host) return;
		if (pages <= 1) {
			host.innerHTML = 'Всего: ' + total;
			return;
		}
		host.innerHTML = pagerHtml(page, pages, total);
		bindPager(host, function (p) {
			page = p;
			loadList();
			var top = document.getElementById('titlo-auto-body');
			if (top && top.scrollIntoView) top.closest('table').scrollIntoView({block: 'start'});
		});
	}

	function renderQueuePager(total, pageSize, pages) {
		var host = document.getElementById('titlo-auto-queue-pager');
		if (!host) return;
		queuePage = Math.max(1, Math.min(queuePage, pages || 1));
		if (pages <= 1) {
			host.innerHTML = total ? ('В журнале: ' + total) : '';
			return;
		}
		host.innerHTML = pagerHtml(queuePage, pages, total, 'по ' + pageSize);
		bindPager(host, function (p) {
			queuePage = p;
			loadQueue();
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
				'Поставьте категорию снова в очередь в режиме «Только анализ».' +
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
				else {
					delete selected[id];
					selectAllMatching = false;
				}
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

	function dupMembers(d) {
		return (d.others || []).concat(d.self ? [d.self] : []);
	}

	function dupStructure(d) {
		var members = dupMembers(d);
		var byId = {};
		members.forEach(function (m) { byId[m.id] = m; });
		var kids = {};
		var roots = [];
		members.forEach(function (m) {
			if (byId[m.parent_id]) (kids[m.parent_id] = kids[m.parent_id] || []).push(m);
			else roots.push(m);
		});
		return {byId: byId, kids: kids, roots: roots};
	}

	function dupMemberRef(m) {
		return '<b>#' + m.id + ' ' + escapeHtml(m.name) + '</b>';
	}

	function dupInGroupChildIds(d) {
		var st = dupStructure(d);
		var ids = [];
		Object.keys(st.kids).forEach(function (pid) {
			st.kids[pid].forEach(function (m) { ids.push(m.id); });
		});
		return ids;
	}

	function dupParentLine(d) {
		var p = d.parent;
		if (!p) {
			var st = dupStructure(d);
			var linked = st.roots.filter(function (r) { return st.kids[r.id]; });
			var alone = st.roots.filter(function (r) { return !st.kids[r.id]; });
			if (!linked.length) {
				return '<span class="titlo-grp__warn">Общего родителя нет</span> — это разные ветки каталога. Оставьте фразу одной категории, у остальных смените фразу или исключите.';
			}
			var lines = linked.map(function (r) {
				return 'Внутри группы: ' + dupMemberRef(r) + ' — родитель для ' +
					st.kids[r.id].map(dupMemberRef).join(', ') + '. Обычно прорабатывают родителя, дочерние исключают.';
			});
			if (alone.length) {
				lines.push('<span class="titlo-grp__warn">Отдельная ветка:</span> ' + alone.map(function (m) {
					return dupMemberRef(m) + (m.parent_name ? ' в «' + escapeHtml(m.parent_name) + '»' : '');
				}).join(', ') + ' — ей лучше задать свою фразу или исключить.');
			}
			return lines.join('<br>');
		}
		var meta = [];
		meta.push(p.titlo_phrase
			? (p.same_phrase ? 'та же фраза' : ('своя фраза «' + escapeHtml(p.titlo_phrase) + '»'))
			: 'без фразы');
		meta.push(p.has_detail ? ('описание ' + p.detail_chars + ' зн.') : 'без описания');
		if (!p.active) meta.push('неактивен');
		return 'Общий родитель: <a href="' + escapeHtml(p.single_url) + '"><b>' + escapeHtml(p.name) + '</b></a>' +
			' <span class="titlo-grp__meta">#' + p.id + ' · ' + meta.join(' · ') + '</span>' +
			(p.url ? ' <a class="titlo-grp__ext" href="' + safeHref(p.url) + '" target="_blank" rel="noopener">на сайте ↗</a>' : '') +
			'<span class="titlo-grp__advice">' + (d.role === 'parent'
				? 'Обычно прорабатывают родителя, дочерние исключают.'
				: 'Обычно прорабатывают родителя, а эти дочерние исключают.') + '</span>';
	}

	function dupChildIds(d) {
		var pid = d.parent ? d.parent.id : 0;
		return (d.member_ids || []).filter(function (id) { return id !== pid; });
	}

	function dupHiddenMembersHtml(d, pageIds) {
		var hidden = (d.others || []).filter(function (o) { return !pageIds[o.id]; });
		if (!hidden.length) return '';
		return '<div class="titlo-grp__hidden"><span class="titlo-grp__hidden-label">Ещё в группе, но скрыты фильтром:</span> ' + hidden.map(function (o) {
			var why = o.auto_done ? 'уже прошла автопроработку' : (o.detail_chars ? ('есть описание ' + o.detail_chars + ' зн.') : 'на другой странице');
			return '<span class="titlo-grp__chip"><a href="' + escapeHtml(o.single_url) + '">' + escapeHtml(o.name) + '</a>' +
				'<span class="titlo-grp__meta"> #' + o.id + (o.parent_name ? ' · в «' + escapeHtml(o.parent_name) + '»' : '') + ' · ' + why + '</span>' +
				(o.url ? ' <a class="titlo-grp__ext" href="' + safeHref(o.url) + '" target="_blank" rel="noopener">сайт ↗</a>' : '') + '</span>';
		}).join('') + '</div>';
	}

	function dupGroupHeadHtml(it, pageIds) {
		var d = it.phrase_dup;
		var children = d.parent ? dupChildIds(d) : dupInGroupChildIds(d);
		var btn = '';
		if (children.length) {
			btn = '<button type="button" class="adm-btn titlo-grp__btn" data-skip-ids="' + children.join(',') + '" data-skip="1">' +
				'Исключить дочерние (' + children.length + ')</button>' +
				'<button type="button" class="adm-btn titlo-grp__btn" data-skip-ids="' + children.join(',') + '" data-skip="1" data-noindex="1"' +
				' title="Исключить из автопроработки и закрыть страницы дочерних категорий (meta robots noindex, nofollow). Товары не закрываются.">' +
				'… и закрыть noindex</button>';
		}
		return '<tr class="titlo-grp-head"><td colspan="' + COLSPAN + '">' +
			'<div class="titlo-grp">' +
			'<div class="titlo-grp__main">' +
			'<div class="titlo-grp__title"><span class="titlo-grp__phrase">«' + escapeHtml(it.titlo_phrase) + '»</span>' +
			'<span class="titlo-grp__count">одна фраза у ' + d.count + ' категорий</span></div>' +
			'<div class="titlo-grp__parent">' + dupParentLine(d) + '</div>' +
			dupHiddenMembersHtml(d, pageIds || {}) +
			'</div>' +
			(btn ? '<div class="titlo-grp__actions">' + btn + '</div>' : '') +
			'</div></td></tr>';
	}

	function nameCellHtml(it) {
		var d = it.phrase_dup;
		var out = '<div class="titlo-auto-name">';
		if (d && d.role === 'child') out += '<span class="titlo-auto-dup__indent">↳</span>';
		out += '<span class="titlo-auto-name__text">' + escapeHtml(it.name) + '</span>';
		if (d && d.role === 'parent') out += ' <span class="titlo-tag titlo-tag--parent">родитель</span>';
		if (it.noindex) out += ' <span class="titlo-tag titlo-tag--noindex">noindex</span>';
		if (it.auto_skip) out += ' <span class="titlo-tag">исключена</span>';
		out += '</div>';
		if (it.parent_name) {
			out += '<div class="titlo-auto-name__path">в «' + escapeHtml(it.parent_name) + '»</div>';
		}
		return out;
	}

	function actionsCellHtml(it) {
		var out = '<div class="titlo-row-actions__links"><a href="' + escapeHtml(it.single_url) + '">проработка</a>' +
			(it.url ? ' · <a href="' + safeHref(it.url) + '" target="_blank" rel="noopener">сайт ↗</a>' : '') + '</div>' +
			'<div class="titlo-row-actions__btns">';
		if (it.auto_skip) {
			out += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.id + '" data-skip="0">вернуть</button>';
		} else {
			out += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.id + '" data-skip="1" title="Не прорабатывать автоматически">исключить</button>';
			if (!it.noindex) {
				out += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.id + '" data-skip="1" data-noindex="1"' +
					' title="Исключить и закрыть страницу категории (meta robots noindex, nofollow). Товары не закрываются.">+ noindex</button>';
			}
		}
		if (it.noindex) {
			out += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.id + '" data-skip="" data-noindex="0" title="Убрать noindex со страницы категории">открыть индекс</button>';
		}
		return out + '</div>';
	}

	function queueEntityCellHtml(it) {
		var sid = ' data-status-id="titlo-auto-requeue-status"';
		var out = '<a href="' + escapeHtml(it.single_url) + '">' + it.entity_id + '</a>' +
			(it.url ? ' · <a href="' + safeHref(it.url) + '" target="_blank" rel="noopener" title="Открыть на сайте">↗</a>' : '');
		var tags = [];
		if (it.auto_skip) tags.push('<span class="titlo-tag titlo-tag--skip">исключена</span>');
		if (it.noindex) tags.push('<span class="titlo-tag titlo-tag--noindex">noindex</span>');
		if (tags.length) out += '<div>' + tags.join(' ') + '</div>';
		var btns = '';
		if (!it.auto_skip) {
			btns += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.entity_id + '" data-skip="1"' + sid +
				' title="Не прорабатывать автоматически; задания «в очереди» и «ошибка» снимутся">исключить</button>';
			if (!it.noindex) {
				btns += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.entity_id + '" data-skip="1" data-noindex="1"' + sid +
					' title="Исключить и закрыть страницу категории (meta robots noindex, nofollow). Товары не закрываются.">+ noindex</button>';
			}
		} else {
			btns += '<button type="button" class="titlo-mini-btn" data-skip-ids="' + it.entity_id + '" data-skip="0"' + sid + '>вернуть</button>';
		}
		return out + '<div class="titlo-row-actions__btns">' + btns + '</div>';
	}

	function setAutoSkip(ids, skip, noindex, statusId) {
		statusId = statusId || 'titlo-auto-list-status';
		if (noindex === '1' && !window.confirm('Закрыть от индексации (meta robots noindex) категорий: ' + ids.length +
			'?\nСтраницы категорий выпадут из поиска; товары внутри останутся открытыми.')) {
			return Promise.resolve();
		}
		setStatus(statusId, 'Сохраняю…');
		return post('auto_section_skip', {ids: ids.join(','), skip: skip, noindex: noindex}).then(function (res) {
			if (!res.ok) {
				setStatus(statusId, res.error || 'Ошибка', true);
				return;
			}
			ids.forEach(function (id) { delete selected[id]; });
			loadQueue();
			return loadList().then(function () {
				var msg = [];
				if (skip === '1') msg.push('Исключено: ' + ids.length + ' (вернуть — фильтр «Исключённые из автопроработки»)');
				if (res.cancelled_jobs) msg.push('снято заданий из очереди: ' + res.cancelled_jobs);
				if (skip === '0') msg.push('Вернули в работу: ' + ids.length);
				if (noindex === '1') msg.push('noindex: ' + (res.noindex_count || 0));
				if (noindex === '0') msg.push('индексация открыта: ' + (res.noindex_count || 0));
				setStatus(statusId, msg.join(' · '));
			});
		});
	}

	function bindSkipEvents(body) {
		Array.prototype.forEach.call(body.querySelectorAll('[data-skip-ids]'), function (el) {
			el.onclick = function (e) {
				e.preventDefault();
				var ids = (el.getAttribute('data-skip-ids') || '').split(',').map(function (x) { return parseInt(x, 10) || 0; })
					.filter(function (x) { return x > 0; });
				if (!ids.length) return;
				setAutoSkip(ids, el.getAttribute('data-skip') || '', el.getAttribute('data-noindex') || '', el.getAttribute('data-status-id') || '');
			};
		});
	}

	function updateNoindexBar(filter, total) {
		var bar = document.getElementById('titlo-auto-noindex-bar');
		if (!bar) return;
		var show = filter === 'skipped_open' && total > 0;
		bar.hidden = !show;
		if (show) {
			document.getElementById('titlo-auto-noindex-msg').textContent =
				'Исключены, но страница открыта для индексации: ' + total + '.';
			document.getElementById('titlo-auto-noindex-all').value = 'Закрыть noindex все найденные (' + total + ')';
		}
	}

	function closeNoindexAllFound() {
		var fp = listFilterParams();
		return post('auto_list_ids', {
			entity_type: 'S',
			q: fp.q,
			filter: 'skipped_open',
			section_ids: fp.section_ids
		}).then(function (res) {
			var ids = (res && res.ok && res.ids) ? res.ids : [];
			if (!ids.length) {
				setStatus('titlo-auto-list-status', 'Нечего закрывать');
				return;
			}
			return setAutoSkip(ids, '', '1');
		});
	}

	function emptyListHtml(res, fp) {
		var st = res.branch_stats;
		if (!st) {
			return 'Нет категорий по фильтру (нужна короткая фраза)';
		}
		var namesUrl = 'titlo_relevance_section_names.php?lang=ru&section_ids=' + encodeURIComponent(fp.section_ids || '');
		var lines = ['<b>В выбранной ветке ' + st.total + ' категорий, но под этот фильтр ни одна не подходит.</b>'];
		if (st.no_phrase) {
			lines.push('Без короткой фразы: <b>' + st.no_phrase + '</b> — автопроработка берёт только категории с фразой. ' +
				'Сначала сгенерируйте фразы: <a href="' + namesUrl + '">Проверка названий категорий по этой ветке</a>.');
		}
		if (st.with_detail) {
			lines.push('С описанием: ' + st.with_detail + ' — их видно в фильтре «Есть описание (без отметки авто)».');
		}
		if (st.done) lines.push('Уже прошли автопроработку: ' + st.done + ' (фильтр «Прошли автопроработку»).');
		if (st.skipped) lines.push('Исключены: ' + st.skipped + ' (фильтр «Исключённые из автопроработки»).');
		return '<div class="titlo-auto-empty">' + lines.join('<br>') + '</div>';
	}

	function loadList() {
		setStatus('titlo-auto-list-status', 'Загрузка…');
		var fp = listFilterParams();
		return post('auto_list_sections', {
			page: page,
			page_size: 25,
			q: fp.q,
			filter: fp.filter,
			sort: fp.sort,
			section_ids: fp.section_ids
		}).then(function (res) {
			var body = document.getElementById('titlo-auto-body');
			if (!res.ok) {
				body.innerHTML = '<tr><td colspan="' + COLSPAN + '">' + escapeHtml(res.error || 'Ошибка') + '</td></tr>';
				setStatus('titlo-auto-list-status', res.error || 'Ошибка', true);
				return;
			}
			listTotal = res.total || 0;
			updateNoindexBar(fp.filter, listTotal);
			var rows = [];
			var prevGroup = '';
			var pageIds = {};
			(res.items || []).forEach(function (it) { pageIds[it.id] = true; });
			(res.items || []).forEach(function (it) {
				var checked = !!selected[it.id];
				var d = it.phrase_dup;
				var cls = [];
				if (d) {
					if (d.key !== prevGroup) rows.push(dupGroupHeadHtml(it, pageIds));
					cls.push('titlo-auto-dup', 'titlo-auto-dup--' + d.role);
				}
				prevGroup = d ? d.key : '';
				if (it.auto_skip) cls.push('titlo-auto-skipped');
				rows.push(
					'<tr data-id="' + it.id + '"' + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + '>' +
					'<td><input type="checkbox" class="titlo-auto-row" value="' + it.id + '"' + (checked ? ' checked' : '') + '></td>' +
					'<td>' + it.id + '</td>' +
					'<td>' + nameCellHtml(it) + '</td>' +
					'<td>' + escapeHtml(it.titlo_phrase) + '</td>' +
					'<td>' + (it.has_detail ? ('есть · ' + it.detail_chars) : 'пусто') + '</td>' +
					'<td class="titlo-auto-score-td">' + scoreCell(it) + '</td>' +
					'<td>' + (it.auto_done ? ('✓ ' + escapeHtml(it.auto_at)) : '—') + '</td>' +
					'<td>' + queueLabel(it.queue) + '</td>' +
					'<td class="titlo-row-actions">' + actionsCellHtml(it) + '</td></tr>'
				);
			});
			body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="' + COLSPAN + '">' + emptyListHtml(res, fp) + '</td></tr>';
			bindListEvents(body);
			bindSkipEvents(body);
			renderPager(res.total || 0, res.page_size || 25, res.pages || 1);
			var qShow = (fp.q || '').trim();
			setStatus(
				'titlo-auto-list-status',
				qShow
					? ('Найдено: ' + (res.total || 0) + ' по «' + qShow + '»')
					: ('Найдено: ' + (res.total || 0))
			);
			var pageRows = document.querySelectorAll('.titlo-auto-row');
			var pageChecked = 0;
			Array.prototype.forEach.call(pageRows, function (cb) { if (cb.checked) pageChecked++; });
			var allCb = document.getElementById('titlo-auto-check-all');
			if (allCb) {
				allCb.checked = pageRows.length > 0 && pageChecked === pageRows.length;
			}
			updateEnqueueBtn();
		});
	}

	function updateQueueRequeueBtn() {
		var n = Object.keys(queueSelected).filter(function (k) { return queueSelected[k]; }).length;
		var btn = document.getElementById('titlo-auto-requeue-selected');
		if (btn) {
			btn.value = 'Запустить выбранные (' + n + ')';
			btn.disabled = !hasKey || n === 0;
		}
		var cancelN = 0;
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-queue-row'), function (cb) {
			if (!cb.checked || cb.disabled) return;
			var st = cb.getAttribute('data-status') || '';
			if (st === 'queued' || st === 'failed') cancelN++;
		});
		var cBtn = document.getElementById('titlo-auto-cancel-selected');
		if (cBtn) {
			cBtn.value = 'Убрать из очереди (' + cancelN + ')';
			cBtn.disabled = cancelN === 0;
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

	var phraseDrafts = {};
	var PHRASE_MAX = 50;

	function needsPhraseFix(it) {
		if (it.status !== 'failed') return false;
		var err = String(it.error || '');
		return err.indexOf('max 50 chars') >= 0 || err.indexOf('анализатор принимает до') >= 0 || String(it.phrase || '').length > PHRASE_MAX;
	}

	function phraseFixHtml(it) {
		var v = Object.prototype.hasOwnProperty.call(phraseDrafts, it.id) ? phraseDrafts[it.id] : (it.phrase || '');
		return '<div class="titlo-fixph" data-id="' + it.id + '">' +
			'<div class="titlo-fixph__hint">Фраза длиннее ' + PHRASE_MAX + ' зн. — анализатор её не принимает. Сократите и перезапустите:</div>' +
			'<div class="titlo-fixph__row">' +
			'<input type="text" class="titlo-fixph__input" value="' + escapeHtml(v) + '">' +
			'<span class="titlo-fixph__cnt"></span>' +
			'</div>' +
			'<button type="button" class="adm-btn adm-btn-save titlo-fixph__btn">Сохранить и запустить</button>' +
			'<div class="titlo-fixph__msg"></div>' +
			'</div>';
	}

	function bindPhraseFix(host) {
		Array.prototype.forEach.call(host.querySelectorAll('.titlo-fixph'), function (box) {
			var id = parseInt(box.getAttribute('data-id'), 10);
			var input = box.querySelector('.titlo-fixph__input');
			var cnt = box.querySelector('.titlo-fixph__cnt');
			var btn = box.querySelector('.titlo-fixph__btn');
			var msg = box.querySelector('.titlo-fixph__msg');
			function sync() {
				var n = input.value.trim().length;
				cnt.textContent = n + ' / ' + PHRASE_MAX;
				cnt.className = 'titlo-fixph__cnt' + (n > PHRASE_MAX || n === 0 ? ' is-over' : '');
				btn.disabled = n === 0 || n > PHRASE_MAX;
			}
			sync();
			input.addEventListener('input', function () {
				phraseDrafts[id] = input.value;
				sync();
			});
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					if (!btn.disabled) btn.click();
				}
			});
			btn.addEventListener('click', function () {
				btn.disabled = true;
				msg.textContent = 'Сохраняем…';
				msg.className = 'titlo-fixph__msg';
				post('auto_fix_phrase', { id: id, phrase: input.value }).then(function (res) {
					if (res && res.ok) {
						delete phraseDrafts[id];
						msg.textContent = 'Сохранено, задание снова в очереди';
						loadQueue();
						if (typeof loadList === 'function') loadList();
						return;
					}
					msg.textContent = (res && res.error) || 'Ошибка';
					msg.className = 'titlo-fixph__msg is-err';
					sync();
				}).catch(function () {
					msg.textContent = 'Ошибка сети';
					msg.className = 'titlo-fixph__msg is-err';
					sync();
				});
			});
		});
	}

	function focusedPhraseFix() {
		var a = document.activeElement;
		if (!a || !a.classList || !a.classList.contains('titlo-fixph__input')) return null;
		var box = a.closest('.titlo-fixph');
		return box ? { id: box.getAttribute('data-id'), pos: a.selectionStart } : null;
	}

	function restorePhraseFixFocus(host, f) {
		if (!f) return;
		var input = host.querySelector('.titlo-fixph[data-id="' + f.id + '"] .titlo-fixph__input');
		if (!input) return;
		input.focus();
		try { input.setSelectionRange(f.pos, f.pos); } catch (e) {}
	}

	function formatQueueError(it) {
		var err = (it.error || '').trim();
		if (needsPhraseFix(it)) return phraseFixHtml(it);
		if (!err) return '';
		if (err === 'cancelled_by_user') return '';
		if (err === 'analysis_failed' || err === '1') {
			return 'анализ в Titlo не сохранился · ' + escapeHtml(it.step_label || it.step || 'анализ');
		}
		if (it.status === 'running' || it.status === 'cancelled') {
			return '<span class="titlo-queue-note">' + escapeHtml(err) + '</span>';
		}
		return escapeHtml(err);
	}

	function canSelectQueueJob(it) {
		var st = it.status || '';
		return st === 'failed' || st === 'skipped' || st === 'done' || st === 'queued' || st === 'cancelled';
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

	function queueFilters() {
		return {
			status: (document.getElementById('titlo-q-status') || {}).value || '',
			run_mode: (document.getElementById('titlo-q-run-mode') || {}).value || '',
			engine: (document.getElementById('titlo-q-engine') || {}).value || '',
			top: (document.getElementById('titlo-q-top') || {}).value || '',
			q: (document.getElementById('titlo-q-q') || {}).value || ''
		};
	}

	function formatQueueEngine(it) {
		var eng = escapeHtml(it.engine_label || '—');
		var reg = escapeHtml(it.region_label || '');
		if (!reg || reg === '—' || reg === eng) return eng;
		return eng + '<div style="font-size:11px;color:#64748b">' + reg + '</div>';
	}

	function loadQueue() {
		var f = queueFilters();
		return post('auto_queue_status', {
			page: queuePage,
			limit: QUEUE_PAGE_SIZE,
			batch_key: '',
			entity_type: entityType,
			tick: hasKey ? 1 : 0,
			status: f.status,
			run_mode: f.run_mode,
			engine: f.engine,
			top: f.top,
			q: f.q
		}).then(function (res) {
			if (!res.ok) return;
			if (res.page) queuePage = parseInt(res.page, 10) || queuePage;
			var counts = res.counts || {};
			var cEl = document.getElementById('titlo-auto-queue-counts');
			cEl.textContent = 'В работе: ' + (res.active || 0)
				+ ' · в очереди ' + (counts.queued || 0)
				+ ' · выполняются ' + (counts.running || 0)
				+ ' · готово ' + (counts.done || 0)
				+ ' · текст не писали ' + (counts.skipped || 0)
				+ ' · снято ' + (counts.cancelled || 0)
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
					'<td>' + queueEntityCellHtml(it) + '</td>' +
					'<td>' + escapeHtml(it.phrase) + '</td>' +
					'<td>' + escapeHtml(it.run_mode_label || it.run_mode || '') + '</td>' +
					'<td>' + formatQueueEngine(it) + '</td>' +
					'<td>' + escapeHtml(it.top_label || '—') + '</td>' +
					'<td>' + escapeHtml(it.result_label || it.status_label || it.status) + '</td>' +
					'<td>' + (it.history_id || '—') + '</td>' +
					'<td>' + formatQueueWhen(it) + '</td>' +
					'<td class="titlo-error">' + formatQueueError(it) + '</td>' +
					'</tr>'
				);
			});
			var fixFocus = focusedPhraseFix();
			body.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="' + QUEUE_COLSPAN + '">Пока пусто</td></tr>';
			bindQueueChecks(body);
			bindPhraseFix(body);
			bindSkipEvents(body);
			restorePhraseFixFocus(body, fixFocus);
			updateQueueRequeueBtn();
			var all = document.getElementById('titlo-auto-queue-check-all');
			if (all) all.checked = false;
			renderQueuePager(res.total || 0, res.page_size || QUEUE_PAGE_SIZE, res.pages || 1);

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

	document.getElementById('titlo-auto-run-mode').onchange = function () {
		syncModeUi();
		loadPrompts();
	};

	document.getElementById('titlo-auto-noindex-all').onclick = function (e) {
		e.preventDefault();
		closeNoindexAllFound();
	};

	document.getElementById('titlo-auto-check-all').onchange = function () {
		var on = this.checked;
		selectAllMatching = false;
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-row'), function (cb) {
			cb.checked = on;
			var id = parseInt(cb.value, 10);
			if (on) selected[id] = true;
			else delete selected[id];
		});
		updateEnqueueBtn();
	};

	document.getElementById('titlo-auto-sel-all-match').onclick = function () {
		var btn = this;
		btn.disabled = true;
		setStatus('titlo-auto-run-status', 'Загружаем все ID по фильтру…');
		var fp = listFilterParams();
		post('auto_list_ids', {
			entity_type: entityType,
			q: fp.q,
			filter: fp.filter,
			section_ids: fp.section_ids
		}).then(function (res) {
			btn.disabled = false;
			if (!res.ok) {
				setStatus('titlo-auto-run-status', res.error || 'Не удалось выбрать все', true);
				return;
			}
			selected = {};
			(res.ids || []).forEach(function (id) {
				selected[parseInt(id, 10)] = true;
			});
			selectAllMatching = true;
			Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-row'), function (cb) {
				var id = parseInt(cb.value, 10);
				cb.checked = !!selected[id];
			});
			var allCb = document.getElementById('titlo-auto-check-all');
			if (allCb) {
				var pageRows = document.querySelectorAll('.titlo-auto-row');
				var pageChecked = 0;
				Array.prototype.forEach.call(pageRows, function (cb) { if (cb.checked) pageChecked++; });
				allCb.checked = pageRows.length > 0 && pageChecked === pageRows.length;
			}
			updateEnqueueBtn();
			var n = selectedCount();
			var note = res.truncated
				? ('Выбрано ' + n + ' из ' + (res.total || n) + ' (лимит ' + (res.limit || n) + ' за раз)')
				: ('Выбрано все найденные: ' + n);
			setStatus('titlo-auto-run-status', note);
		}).catch(function () {
			btn.disabled = false;
			setStatus('titlo-auto-run-status', 'Ошибка запроса', true);
		});
	};

	document.getElementById('titlo-auto-sel-clear').onclick = function () {
		clearSelection();
		setStatus('titlo-auto-run-status', 'Выбор снят');
	};

	document.getElementById('titlo-auto-reload').onclick = function () {
		page = 1;
		clearSelection();
		loadList();
	};
	document.getElementById('titlo-auto-filter').onchange = function () {
		page = 1;
		clearSelection();
		loadList();
	};
	document.getElementById('titlo-auto-sort').onchange = function () {
		page = 1;
		clearSelection();
		loadList();
	};
	if (window.TitloSectionBranch) {
		TitloSectionBranch.bind(function () {
			page = 1;
			clearSelection();
			loadList();
		});
	}
	(function bindLiveSearch() {
		var inp = document.getElementById('titlo-auto-q');
		if (!inp) return;
		var timer = null;
		function runSearch() {
			timer = null;
			page = 1;
			clearSelection();
			loadList();
		}
		inp.addEventListener('input', function () {
			if (timer) clearTimeout(timer);
			timer = setTimeout(runSearch, 350);
		});
		inp.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				if (timer) clearTimeout(timer);
				page = 1;
				clearSelection();
				loadList();
			}
		});
		inp.addEventListener('focus', function () {
			if (window.TitloSectionBranch && typeof TitloSectionBranch.close === 'function') {
				TitloSectionBranch.close();
			}
		});
		var clearBtn = document.getElementById('titlo-auto-q-clear');
		if (clearBtn) {
			clearBtn.onclick = function () {
				if (timer) clearTimeout(timer);
				inp.value = '';
				page = 1;
				clearSelection();
				loadList();
				inp.focus();
			};
		}
	})();

	document.getElementById('titlo-auto-enqueue').onclick = function () {
		var ids = Object.keys(selected).filter(function (k) { return selected[k]; }).map(function (k) { return parseInt(k, 10); });
		if (!ids.length) return;
		setStatus('titlo-auto-run-status', 'Ставим в очередь…');
		var analysis = analysisFields();
		post('auto_enqueue_batch', {
			entity_type: entityType,
			ids: JSON.stringify(ids),
			run_mode: document.getElementById('titlo-auto-run-mode').value,
			prompt_detail_id: document.getElementById('titlo-auto-prompt-detail').value || 0,
			prompt_preview_id: 0,
			gen_preview: '0',
			existing_policy: document.getElementById('titlo-auto-policy').value,
			tlp_missing_limit: document.getElementById('titlo-auto-tlp-missing').value,
			tlp_diff_limit: document.getElementById('titlo-auto-tlp-diff').value,
			engine: analysis.engine,
			region: analysis.region,
			top: analysis.top
		}).then(function (res) {
			if (!res.ok) {
				setStatus('titlo-auto-run-status', res.error || 'Не удалось поставить', true);
				return;
			}
			lastBatchKey = res.batch_key || '';
			queuePage = 1;
			clearSelection();
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
	document.getElementById('titlo-auto-queue-select-queued').onclick = function () {
		selectQueueByPred(function (cb) {
			return (cb.getAttribute('data-status') || '') === 'queued';
		});
	};
	document.getElementById('titlo-auto-queue-select-no-text').onclick = function () {
		selectQueueByPred(function (cb) {
			var st = cb.getAttribute('data-status') || '';
			var mode = cb.getAttribute('data-run-mode') || '';
			return st === 'skipped' || (st === 'done' && mode === 'analyze_only');
		});
	};

	document.getElementById('titlo-auto-cancel-selected').onclick = function () {
		var ids = [];
		Array.prototype.forEach.call(document.querySelectorAll('.titlo-auto-queue-row'), function (cb) {
			if (!cb.checked || cb.disabled) return;
			var st = cb.getAttribute('data-status') || '';
			if (st !== 'queued' && st !== 'failed') return;
			ids.push(parseInt(cb.value, 10));
		});
		if (!ids.length) return;
		setStatus('titlo-auto-requeue-status', 'Снимаем с очереди…');
		post('auto_cancel_jobs', {
			ids: JSON.stringify(ids),
			entity_type: entityType
		}).then(function (res) {
			if (!res.ok) {
				setStatus('titlo-auto-requeue-status', res.error || 'Не удалось снять', true);
				return;
			}
			ids.forEach(function (id) { delete queueSelected[id]; });
			updateQueueRequeueBtn();
			var extra = (res.skipped || 0) > 0 ? (', пропущено: ' + res.skipped) : '';
			setStatus('titlo-auto-requeue-status', 'Снято с очереди: ' + (res.cancelled || 0) + extra);
			loadList();
			loadQueue();
		});
	};

	document.getElementById('titlo-auto-requeue-selected').onclick = function () {
		var ids = Object.keys(queueSelected).filter(function (k) { return queueSelected[k]; }).map(function (k) { return parseInt(k, 10); });
		if (!ids.length) return;
		setStatus('titlo-auto-requeue-status', 'Ставим в очередь…');
		var analysis = analysisFields();
		post('auto_requeue_jobs', {
			ids: JSON.stringify(ids),
			entity_type: 'S',
			run_mode: document.getElementById('titlo-auto-run-mode').value,
			existing_policy: document.getElementById('titlo-auto-policy').value,
			prompt_detail_id: document.getElementById('titlo-auto-prompt-detail').value || 0,
			prompt_preview_id: 0,
			gen_preview: '0',
			tlp_missing_limit: document.getElementById('titlo-auto-tlp-missing').value,
			tlp_diff_limit: document.getElementById('titlo-auto-tlp-diff').value,
			engine: analysis.engine,
			region: analysis.region,
			top: analysis.top
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

	function applyQueueFilters() {
		queuePage = 1;
		loadQueue();
	}
	['titlo-q-status', 'titlo-q-run-mode', 'titlo-q-engine', 'titlo-q-top'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) el.onchange = applyQueueFilters;
	});
	document.getElementById('titlo-q-apply').onclick = applyQueueFilters;
	document.getElementById('titlo-q-q').onkeydown = function (e) {
		if (e.key === 'Enter') { e.preventDefault(); applyQueueFilters(); }
	};
	document.getElementById('titlo-q-clear').onclick = function () {
		document.getElementById('titlo-q-status').value = '';
		document.getElementById('titlo-q-run-mode').value = '';
		document.getElementById('titlo-q-engine').value = '';
		document.getElementById('titlo-q-top').value = '';
		document.getElementById('titlo-q-q').value = '';
		applyQueueFilters();
	};

	syncModeUi();
	loadPrompts().then(loadList).then(loadQueue);
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
