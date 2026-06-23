<?
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$APPLICATION->SetAdditionalCSS("/category-finder/css/admin.css?v=6");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Поиск разделов каталога");
?>
<div class="categoryfinder-admin"
     data-list-url="/category-finder/scripts/server.php"
     data-update-url="/category-finder/scripts/update.php">

    <p class="hint categoryfinder-admin__intro">
        Поиск разделов по уровню вложенности и количеству товаров. Поле «Свои» — элементы, привязанные напрямую к разделу; на витрине каталог также показывает товары из подразделов (<code>INCLUDE_SUBSECTIONS=Y</code>).
    </p>

    <div class="categoryfinder-filters">
        <div class="categoryfinder-filter">
            <label for="cf-filter-iblock">
                ИнфоБлок ID
                <span class="cf-tip" tabindex="0" data-tip="ID инфоблока каталога. По умолчанию 24 — основной каталог vilmed.ru.">(?)</span>
            </label>
            <input type="number" min="1" id="cf-filter-iblock" value="24">
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-level">
                Уровень
                <span class="cf-tip" tabindex="0" data-tip="Уровень вложенности, с которого начинается поиск. Разделы с меньшим уровнем не попадут в результат.">(?)</span>
            </label>
            <input type="number" min="1" id="cf-filter-level" value="1">
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-cnt">
                Свои товары
                <span class="cf-tip" tabindex="0" data-tip="Число элементов, напрямую привязанных к разделу. Не учитывает товары подразделов, даже если они выводятся на витрине.">(?)</span>
            </label>
            <input type="number" min="0" id="cf-filter-cnt" value="0">
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-storefront">
                На витрине
                <span class="cf-tip" tabindex="0" data-tip="Как раздел выглядит для покупателя. «Из подкат.» — своих товаров 0, но в поддереве есть товары. «Пусто» — на странице раздела товаров не будет.">(?)</span>
            </label>
            <select id="cf-filter-storefront">
                <option value="">Все</option>
                <option value="empty">Пусто</option>
                <option value="from_sub">Из подкат.</option>
                <option value="own">Есть свои</option>
                <option value="any">Есть товары</option>
            </select>
        </div>
        <div class="categoryfinder-filter categoryfinder-filter--name">
            <label for="cf-filter-name">
                Название
                <span class="cf-tip" tabindex="0" data-tip="Поиск по части названия раздела. Регистр не важен.">(?)</span>
            </label>
            <input type="text" id="cf-filter-name" value="" placeholder="Часть названия…">
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-active">
                Активность
                <span class="cf-tip" tabindex="0" data-tip="Да — только активные разделы. Нет — только неактивные.">(?)</span>
            </label>
            <select id="cf-filter-active">
                <option value="">Все</option>
                <option value="1" selected>Да</option>
                <option value="0">Нет</option>
            </select>
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-redirect">
                Без редиректа
                <span class="cf-tip" tabindex="0" data-tip="Да — исключить разделы-редиректы (CODE заканчивается на «-r»). Нет — показать только редиректы.">(?)</span>
            </label>
            <select id="cf-filter-redirect">
                <option value="">Все</option>
                <option value="1" selected>Да</option>
                <option value="0">Нет</option>
            </select>
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-without-prod">
                Без товаров
                <span class="cf-tip" tabindex="0" data-tip="Фильтр по отметке UF_WITHOUT_PROD. Так помечают родительские разделы, где товары выводятся из подкатегорий.">(?)</span>
            </label>
            <select id="cf-filter-without-prod">
                <option value="">Все</option>
                <option value="1">Да</option>
                <option value="0">Нет</option>
            </select>
        </div>
        <div class="categoryfinder-filter">
            <label for="cf-filter-duplicate">
                Дубликаты
                <span class="cf-tip" tabindex="0" data-tip="По названию — одинаковые названия. По URL — схожие символьные коды (ЧПУ). Совместный — совпадает и название, и схожий CODE.">(?)</span>
            </label>
            <select id="cf-filter-duplicate">
                <option value="">Нет</option>
                <option value="name">По названию</option>
                <option value="url">По URL</option>
                <option value="both">Совместный</option>
            </select>
        </div>
        <div class="categoryfinder-filter categoryfinder-filter--similarity">
            <label for="cf-filter-duplicate-similarity">
                Схожесть URL, %
                <span class="cf-tip" tabindex="0" data-tip="Порог схожести символьного кода для режимов «По URL» и «Совместный». 100 — только полное совпадение slug.">(?)</span>
            </label>
            <input type="number" min="50" max="100" id="cf-filter-duplicate-similarity" value="85">
        </div>
        <div class="categoryfinder-filter categoryfinder-filter--action">
            <div class="categoryfinder-filter__actions">
                <button type="button" class="btn btn-primary" id="cf-filter-btn" disabled>Найти</button>
                <button type="button" class="btn btn-default" id="cf-filter-reset-btn" disabled>По умолчанию</button>
            </div>
        </div>
    </div>

    <div class="categoryfinder-status categoryfinder-status--loading" id="cf-status" aria-live="polite">Инициализация…</div>

    <div class="categoryfinder-dup-legend" id="cf-dup-legend" hidden></div>

    <div class="categoryfinder-table-wrap">
        <table id="cf-category-table" class="display compact" style="width:100%"></table>
    </div>
</div>

<script src="/category-finder/js/admin.js?v=6"></script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
