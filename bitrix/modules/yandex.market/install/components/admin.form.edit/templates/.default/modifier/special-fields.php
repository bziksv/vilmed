<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

$arResult['SPECIAL_FIELDS'] = [];
$arResult['SPECIAL_FIELDS_SHOWN'] = [];

$arResult['SPECIAL_FIELDS']['refresh-period'] = [
	'REFRESH_PERIOD',
	'CATALOG_REFRESH_PERIOD',
];

$arResult['SPECIAL_FIELDS']['shop-data'] = [
	'SHOP_DATA'
];

$arResult['SPECIAL_FIELDS']['setup-link'] = [
	'SETUP_EXPORT_ALL',
	'SETUP'
];

$arResult['SPECIAL_FIELDS']['promo-type'] = [
	'PROMO_TYPE',
	'PROMO_GIFT_IBLOCK_ID'
];

$arResult['SPECIAL_FIELDS']['permissions'] = [
	'PERMISSIONS',
];

// -- product filter

if (!empty($arParams['PRODUCT_FILTER_FIELDS']))
{
	$arResult['SPECIAL_FIELDS']['product-filter'] = array_keys(array_filter($arResult['FIELDS'], static function(array $field) use ($arParams) {
		return isset($field['FIELD_GROUP']) && in_array($field['FIELD_GROUP'], $arParams['PRODUCT_FILTER_FIELDS'], true);
	}));
}

// -- catalog segment

if (!empty($arParams['CATALOG_SEGMENT_FIELDS']))
{
	$arResult['SPECIAL_FIELDS']['catalog-segment'] = array_keys(array_filter($arResult['FIELDS'], static function(array $field) use ($arParams) {
		if (isset($field['HIDDEN']) && $field['HIDDEN'] === 'Y') { return false; }

		return isset($field['FIELD_GROUP']) && in_array($field['FIELD_GROUP'], $arParams['CATALOG_SEGMENT_FIELDS'], true);
	}));
}

// -- external_id

$arResult['SPECIAL_FIELDS']['external-id'] = [
	'EXTERNAL_ID',
];

foreach ($arResult['TABS'] as $tab)
{
	if (!is_array($tab['FIELDS']) || !in_array('EXTERNAL_ID', $tab['FIELDS'], true)) { continue; }

	foreach ($tab['FIELDS'] as $fieldName)
	{
		if (mb_strpos($fieldName, 'EXTERNAL_SETTINGS') === 0)
		{
			$arResult['SPECIAL_FIELDS']['external-id'][] = $fieldName;
		}
	}
}

// finalize

$arResult['SPECIAL_FIELDS_MAP'] = [];

foreach ($arResult['SPECIAL_FIELDS'] as $specialKey => $fields)
{
	foreach ($fields as $field)
	{
		$arResult['SPECIAL_FIELDS_MAP'][$field] = $specialKey;
	}
}