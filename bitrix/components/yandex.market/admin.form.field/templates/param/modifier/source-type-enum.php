<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Export\ParamValue;
use Yandex\Market\Export\Entity;

/** @var array $arParams */

$arResult['SOURCE_TYPE_ENUM'] = [];

// recommendation

$arResult['RECOMMENDATION_TYPE'] = ParamValue\Table::SOURCE_TYPE_RECOMMENDATION;

$arResult['SOURCE_TYPE_ENUM'][$arResult['RECOMMENDATION_TYPE']] = [
	'ID' => $arResult['RECOMMENDATION_TYPE'],
	'VALUE' => ParamValue\Table::getFieldEnumTitle('SOURCE_TYPE', $arResult['RECOMMENDATION_TYPE']),
	'CONTROL' => Entity\Manager::CONTROL_SELECT,
	'VARIABLE' => false,
	'TEMPLATE' => false,
];

// sources

foreach (Entity\Manager::getSourceTypeList() as $sourceType)
{
	$source = Entity\Manager::getSource($sourceType);

	if ($source->isSelectable() && !$source->isInternal())
	{
		$sourceOption = [
			'ID' => $sourceType,
			'VALUE' => $source->getTitle(),
			'CONTROL' => $source->getControl(),
			'VARIABLE' => $source->isVariable(),
			'TEMPLATE' => $source->isTemplate(),
		];

		if ($source instanceof Entity\Reference\HasFunctions)
		{
			$sourceOption['FUNCTIONS'] = $source->getFunctions($arParams['CONTEXT']);
		}

		$arResult['SOURCE_TYPE_ENUM'][$sourceType] = $sourceOption;
	}
}