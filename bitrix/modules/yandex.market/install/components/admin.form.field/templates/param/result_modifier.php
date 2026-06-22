<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main;

/** @var array $arParams */
/** @var array $arResult */
/** @var CMain $APPLICATION */

try
{
	$this->IncludeLangFile('template.php');

    $arParams['CONTEXT'] = (array)$arParams['CONTEXT'];
    $arParams['GROUPS'] = isset($arParams['GROUPS']) ? $arParams['GROUPS'] : [
        [
            'ACTIVE' => true,
            'INPUT_NAME' => $arParams['INPUT_NAME'],
            'FORMAT' => $arParams['FORMAT'],
            'VALUE' => $arParams['VALUE'],
        ],
    ];
	$arParams += [
		'GROUP_FLAT' => 'N',
		'ENABLED' => true,
	];

	include __DIR__ . '/modifier/tags.php';
	include __DIR__ . '/modifier/source-type-enum.php';
	include __DIR__ . '/modifier/source-field-enum.php';
	include __DIR__ . '/modifier/type-map.php';
	include __DIR__ . '/modifier/recommendation.php';
	include __DIR__ . '/modifier/default-sources.php';
	include __DIR__ . '/modifier/preselect.php';
	include __DIR__ . '/modifier/tag-value.php';

	$pageSettingsGroups = $APPLICATION->GetPageProperty('YAMARKET_PARAM_SETTINGS_GROUPS');

	$arResult['SOURCE_TYPE_ENUM_MAP'] = array_flip(array_keys($arResult['SOURCE_TYPE_ENUM']));
	$arResult['SETTINGS_GROUPS'] = is_array($pageSettingsGroups) ? $pageSettingsGroups : [];
}
catch (Main\SystemException $exception)
{
	$arResult['ERROR'] = $exception->getMessage();
}