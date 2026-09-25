<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/vilmed_perf.php';

if($arResult['ITEM']){
	$arResult['ITEM']['NAME'] = html_entity_decode($arResult['ITEM']['NAME']);
	$arResult['ITEM']["PROPERTIES"]["ARTNUMBER"]["VALUE"] = ($arResult['ITEM']["PROPERTIES"]["ARTNUMBER"]["VALUE"]) ?: $arResult['ITEM']["PROPERTIES"]["CML2_ARTICLE"]["VALUE"];

	$source = vilmedPickLargerPicture(
		is_array($arResult['ITEM']['PREVIEW_PICTURE'] ?? null) ? $arResult['ITEM']['PREVIEW_PICTURE'] : null,
		is_array($arResult['ITEM']['DETAIL_PICTURE'] ?? null) ? $arResult['ITEM']['DETAIL_PICTURE'] : null
	);
	if (is_array($source)) {
		$arResult['ITEM']['PREVIEW_PICTURE'] = vilmedOptimizePicture($source, 360, 360);
	}
}

