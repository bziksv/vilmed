<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Export\Entity;

/** @var array $arParams */

$arResult['SOURCE_FIELD_ENUM'] = [];

foreach ($arResult['SOURCE_TYPE_ENUM'] as $sourceEnumKey => $sourceEnum)
{
	if ($sourceEnum['VARIABLE'] || $sourceEnum['TEMPLATE'] || $sourceEnum['ID'] === $arResult['RECOMMENDATION_TYPE']) { continue; }

    $source = Entity\Manager::getSource($sourceEnum['ID']);
    $hasFields = false;

    foreach ($source->getFields($arParams['CONTEXT']) as $field)
    {
        if (!$field['SELECTABLE']) { continue; }

        $hasFields = true;

        $arResult['SOURCE_FIELD_ENUM'][$sourceEnum['ID'] . '.' . $field['ID']] = [
            'ID' => $field['ID'],
            'VALUE' => $field['VALUE'],
            'TYPE' => $field['TYPE'],
            'SOURCE' => $sourceEnum['ID'],
            'TAG' => isset($field['TAG']) ? (array)$field['TAG'] : null,
            'DEPRECATED' => !empty($field['DEPRECATED']),
        ];
    }

    if (!$hasFields)
    {
        unset($arResult['SOURCE_TYPE_ENUM'][$sourceEnumKey]);
    }
}