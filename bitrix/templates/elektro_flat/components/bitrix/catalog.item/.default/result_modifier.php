<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/vilmed_perf.php';

if($arResult['ITEM']){
	$arResult['ITEM']['NAME'] = html_entity_decode($arResult['ITEM']['NAME']);
	$arResult['ITEM']["PROPERTIES"]["ARTNUMBER"]["VALUE"] = ($arResult['ITEM']["PROPERTIES"]["ARTNUMBER"]["VALUE"]) ?: $arResult['ITEM']["PROPERTIES"]["CML2_ARTICLE"]["VALUE"];

	if (is_array($arResult['ITEM']['PREVIEW_PICTURE'])) {
		$arResult['ITEM']['PREVIEW_PICTURE'] = vilmedOptimizePicture($arResult['ITEM']['PREVIEW_PICTURE'], 280, 280);
	}
}

