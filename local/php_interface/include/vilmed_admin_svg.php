<?php
/**
 * VILMED: сохранять inline SVG в детальном тексте товара.
 * Визуальный редактор Bitrix (fileman html_editor) по умолчанию удаляет <svg>
 * (`svg: {remove:1}` в html-editor.js). Подключаем admin JS, который
 * разрешает svg и дочерние теги в правилах парсера.
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	return;
}

AddEventHandler('main', 'OnEpilog', 'vilmedAdminAllowSvgAssets');

function vilmedAdminIsBackend(): bool
{
	if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
		return true;
	}
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	return strpos($uri, '/bitrix/admin/') !== false;
}

function vilmedAdminAllowSvgAssets(): void
{
	if (!vilmedAdminIsBackend() || !class_exists('\Bitrix\Main\Page\Asset')) {
		return;
	}
	$src = '/local/js/vilmed/admin-allow-svg.js';
	$path = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $src;
	$ver = is_file($path) ? (string)filemtime($path) : '1';
	\Bitrix\Main\Page\Asset::getInstance()->addJs($src . '?v=' . $ver);
}
