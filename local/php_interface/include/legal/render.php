<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Page\Asset;

/**
 * @param string $legalTitle
 * @param string $contentInclude relative to /local/php_interface/include/legal/
 */
function vilmedRenderLegalPage(string $legalTitle, string $contentInclude): void
{
    global $APPLICATION;

    $APPLICATION->SetTitle($legalTitle);
    $APPLICATION->SetPageProperty('title', $legalTitle . ' — ВИЛМЕД');
    $APPLICATION->SetPageProperty('description', $legalTitle . ' интернет-магазина ВИЛМЕД.');

    $cssPath = SITE_TEMPLATE_PATH . '/css/legal.css';
    $cssFile = $_SERVER['DOCUMENT_ROOT'] . $cssPath;
    if (is_file($cssFile)) {
        $version = (string) filemtime($cssFile);
        Asset::getInstance()->addCss($cssPath . '?v=' . $version);
    }

    include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . '/include/legal_page_start.php';
    include $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/legal/' . $contentInclude;
    include $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . '/include/legal_page_end.php';
}
