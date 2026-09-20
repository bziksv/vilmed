<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('titlo.relevance', [
	'Titlo\\Relevance\\ApiClient' => 'lib/ApiClient.php',
	'Titlo\\Relevance\\UrlBuilder' => 'lib/UrlBuilder.php',
	'Titlo\\Relevance\\CatalogRepository' => 'lib/CatalogRepository.php',
	'Titlo\\Relevance\\BatchQueue' => 'lib/BatchQueue.php',
	'Titlo\\Relevance\\Agent' => 'lib/Agent.php',
	'Titlo\\Relevance\\AuditLog' => 'lib/AuditLog.php',
	'Titlo\\Relevance\\Config' => 'lib/Config.php',
	'Titlo\\Relevance\\UserFields' => 'lib/UserFields.php',
	'Titlo\\Relevance\\CountryInName' => 'lib/CountryInName.php',
	'Titlo\\Relevance\\PhraseBulk' => 'lib/PhraseBulk.php',
	'Titlo\\Relevance\\ProductDuplicates' => 'lib/ProductDuplicates.php',
	'Titlo\\Relevance\\SectionDuplicates' => 'lib/SectionDuplicates.php',
	'Titlo\\Relevance\\IndexingControl' => 'lib/IndexingControl.php',
	'Titlo\\Relevance\\Prompts' => 'lib/Prompts.php',
	'Titlo\\Relevance\\WorkHistory' => 'lib/WorkHistory.php',
	'Titlo\\Relevance\\AdminUi' => 'lib/AdminUi.php',
]);

// Очередь/агент пишут datetime через date() — без МСК в админке «−3 часа».
if (function_exists('date_default_timezone_set')) {
	@date_default_timezone_set('Europe/Moscow');
}

// Гарантируем UF_TITLO_PHRASE после обновления файлов без переустановки
$ufVer = '1.1.7';
if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'uf_version', '') !== $ufVer) {
	if (\Bitrix\Main\Loader::includeModule('iblock')) {
		\Titlo\Relevance\UserFields::ensurePhraseField();
		\Titlo\Relevance\ProductDuplicates::ensureFields();
		\Titlo\Relevance\SectionDuplicates::ensureFields();
		\Titlo\Relevance\IndexingControl::ensureFields();
		\Titlo\Relevance\IndexingControl::ensureSectionFields();
		\Bitrix\Main\Config\Option::set('titlo.relevance', 'uf_version', $ufVer);
	}
}

$bulkVer = '1.1.0';
if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'phrase_bulk_schema', '') !== $bulkVer) {
	\Titlo\Relevance\PhraseBulk::ensureTables();
	\Bitrix\Main\Config\Option::set('titlo.relevance', 'phrase_bulk_schema', $bulkVer);
}

$promptSchema = '1.3.2';
if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'prompts_schema', '') !== $promptSchema) {
	\Titlo\Relevance\Prompts::ensureTables();
	\Bitrix\Main\Config\Option::set('titlo.relevance', 'prompts_schema', $promptSchema);
}

$workSchema = '1.2.1';
if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'work_history_schema', '') !== $workSchema) {
	\Titlo\Relevance\WorkHistory::ensureTables();
	\Bitrix\Main\Config\Option::set('titlo.relevance', 'work_history_schema', $workSchema);
}

$queueSchema = \Titlo\Relevance\BatchQueue::SCHEMA_VER;
if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'queue_schema', '') !== $queueSchema) {
	\Titlo\Relevance\BatchQueue::ensureSchema();
	\Bitrix\Main\Config\Option::set('titlo.relevance', 'queue_schema', $queueSchema);
}

AddEventHandler('main', 'OnEpilog', ['\\Titlo\\Relevance\\IndexingControl', 'applyRobotsOnEpilog']);
AddEventHandler('main', 'OnEndBufferContent', ['\\Titlo\\Relevance\\IndexingControl', 'applyRobotsOnBuffer']);

// Иконки меню админки (логотип Titlo + полезные сервисы)
AddEventHandler('main', 'OnProlog', static function () {
	if (!(defined('ADMIN_SECTION') && ADMIN_SECTION === true)) {
		return;
	}
	/** @global CMain $APPLICATION */
	global $APPLICATION;
	if (!is_object($APPLICATION)) {
		return;
	}
	$rel = '/local/modules/titlo.relevance/admin/css/titlo-menu-icons.css';
	$abs = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $rel;
	$v = is_file($abs) ? (string) filemtime($abs) : '1';
	$APPLICATION->SetAdditionalCSS($rel . '?v=' . $v);
});
