<?php
define('NOT_CHECK_FILE_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_KEEP_STATISTIC', 'Y');
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Context;

Loader::includeModule("iblock");

$request = Context::getCurrent()->getRequest();
$protocol = $request->isHttps() ? "https" : "http";
$domain = $request->getHttpHost();
$url = $protocol . "://" . $domain;

$IBLOCK_ID = $_POST['filter_iblock'] ?? false;
$ELEMENT_COUNT = $_POST['filter_cnt'] ?? false;
$DEPTH_LEVEL = $_POST['filter_level'] ?? false;
$ACTIVE = $_POST['filter_active'];
$REDIRECT_FILTER = $_POST['filter_redirect'];
$WITHOUT_PROD = $_POST['filter_without_prod'];

$data = [];

if ($IBLOCK_ID && $DEPTH_LEVEL) {
	
	$arFilter = [
		'IBLOCK_ID' => $IBLOCK_ID,
		'>=DEPTH_LEVEL' => $DEPTH_LEVEL,
	];
	
	if ($ACTIVE == "1") {
		$arFilter['ACTIVE'] = "Y";
	} else if ($ACTIVE == "0") {
		$arFilter['ACTIVE'] = "N";
	}
	
	if ($REDIRECT_FILTER == "1") {
		$arFilter['!CODE'] = "%-r";
	} else if ($REDIRECT_FILTER == "0") {
		$arFilter['CODE'] = "%-r";
	}
	
	if ($WITHOUT_PROD == "1") {
		$arFilter['UF_WITHOUT_PROD'] = true;
	} else if ($WITHOUT_PROD == "0") {
		$arFilter['UF_WITHOUT_PROD'] = false;
	}
	
	$SECTIONS = [];
	$db_list = CIBlockSection::GetList([], $arFilter, true, ['ID']);
	while($ar_result = $db_list->GetNext())
	{
		$SECTIONS[$ar_result['ELEMENT_CNT']][] = $ar_result['ID'];
	}
	
	$arOrder = ['depth_level' => 'asc', 'name' => 'asc'];
	$arSelects = ['IBLOCK_ID', 'ACTIVE', 'ID', 'CODE', 'NAME', 'SECTION_PAGE_URL', 'DEPTH_LEVEL', 'UF_WITHOUT_PROD'];
	$arFilters = ['IBLOCK_ID' => $IBLOCK_ID, 'ID' => []];

	if (is_numeric($ELEMENT_COUNT)) {
		if (isset($SECTIONS[$ELEMENT_COUNT])) {
			$arFilters['ID'] = $SECTIONS[$ELEMENT_COUNT];
		}
	} else if(is_string($ELEMENT_COUNT)) {
		$arIds = [];
			
		array_walk_recursive($SECTIONS, function ($item) use (&$arIds) {
			$arIds[] = $item;
		});
			
		$arFilters['ID'] = $arIds;
	}
	
	if (count($arFilters['ID']) > 0) {
		$db_list = CIBlockSection::GetList($arOrder, $arFilters, true, $arSelects);
		$i = 0;
		while($ar_result = $db_list->GetNext())
		{
			$i++;
			
			$SECTION_ID = $ar_result['ID'];
			$NAME = $ar_result['NAME'];
			$CODE = $ar_result['CODE'];
			$adminUrl = "/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=$IBLOCK_ID&ID=$SECTION_ID&lang=ru";
			$siteUrl = $ar_result['SECTION_PAGE_URL'];
			$urlText = implode([$url, $siteUrl]);

			$data[] = [
				$i,
				$ar_result['DEPTH_LEVEL'],
				$ar_result['ID'],
				$ar_result['ELEMENT_CNT'],
				($ar_result['ACTIVE'] == 'Y') ? 'Да' : 'Нет',
				"<a href='$adminUrl' target='_blank'>$NAME</a>",
				"<a href='$siteUrl' target='_blank'>$urlText</a>",
				$ar_result['UF_WITHOUT_PROD'] ? true : false,
			];
		}		
	}
}

echo json_encode([
	'draw' => $_POST['draw'],
    'data' => $data,
	'recordsTotal' => count($data)
]);
