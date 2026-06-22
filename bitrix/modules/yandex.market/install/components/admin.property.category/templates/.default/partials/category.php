<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;

/** @var array $arParams */

$parentCategory = !empty($arParams['PARENT_VALUE']['CATEGORY']) ? (string)$arParams['PARENT_VALUE']['CATEGORY'] : '';
$options = sprintf('<option value="">%s</option>', Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_NO_VALUE'));
$copyButton = '';
$copyClass = '';

if (!empty($arParams['VALUE']['CATEGORY']))
{
	$options .= '<option selected>' . $arParams['VALUE']['CATEGORY'] . '</option>';
}

if (!isset($arParams['COPY_BUTTON']) || $arParams['COPY_BUTTON'] !== 'N')
{
	$copyClass = 'with--copy';
	$copyTitle = Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_COPY');
	$copyButton = <<<HTML
		<button class="ym-category-origin__copy" type="button" title="{$copyTitle}" data-entity="copy">{$copyTitle}</button>
HTML;
}

return <<<SELECT
    <div class="ym-category-origin {$copyClass}" data-entity="category">
    	<input type="hidden" data-entity="parentCategory" value="{$parentCategory}" />
        <select class="ym-category-origin__control" name="{$arParams['CONTROL_NAME']}[CATEGORY]">{$options}</select>
       	{$copyButton}
    </div>
SELECT;
