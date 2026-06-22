<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

$moduleCode = 'cookiesaccept';
$defaultSize = 46; 
$arResult = array();
$arParams = array(
	'TEXTVER' => '1',
	'MAINTEXT' => '',
	'TEXTBTN' => '',
	'PADDINGSIZE' => '12',
	'TOP' => "0",	
	'TOPORBOTTOM' => '2',
	'FIXED' => 'Y',

	'POSITION' => 'left',
	'ZINDEX' => 999,	
	'NOINDEX' => 'N',	
	'HIDE_MOBILE' => 'N',	
	'HIDE_PC' => 'N',	
	'SETSTYLE' => 'native',
	'BTNBG' => '#68BEC1',
	'BTNCOLOR' => '#FFFFFF',
	'ANIMATION' => 'none',
	'FONT' => 21,
	'SIZE' => 46, 
	'BTNBORDER' => 0,
	'BTNBORDERCOLOR' => '#938D8D',
	'BTNBORDERCOLOR_HOVER' => '#8B98A8',
	'VMARGIN' => 5,
	'HMARGIN' => 2,
	'MARGIN' => 0,
	'BTNOPACITY' => 100,
);

foreach ($arParams as $code => $dValue) {
	$arResult[$code] = COption::GetOptionString("niges.".$moduleCode, $code, $dValue, SITE_ID);
}

/*
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/components/niges/cookiesaccept/templates/.default/.cookiesacceptlist.php';
$arSocNet = $arPosition;
unset($arSocNet['SIZE']);

$arSort = @unserialize(COption::GetOptionString("niges.cookiesaccept", "SORT_ICO", false, SITE_ID));
foreach ($arSocNet as $name => $arItem) {
	$arSocNet[$name]['SORT'] = $arSort[$name];
}

if (!function_exists('socSort')) {
	function socSort($a, $b) {
		if ($a['SORT'] == $b['SORT']) {
			return 0;
		}
		return ($a['SORT'] < $b['SORT']) ? -1 : 1;
	}
}

uasort($arSocNet, "socSort");

foreach ($arSocNet as $name => $pos) {
	if ($link = COption::GetOptionString("niges.cookiesaccept", $name, false, SITE_ID)) {
		$arResult['ITEMS'][] = array(
			'NAME' => $name,
			'LINK' => $link,
			'SORT' => $pos['SORT'],
		);
	}
}

if ($arPosition && $arResult['ITEMS'][0]) {
	foreach ($arResult['ITEMS'] as $i => $arItem) {
		$arResult['ITEMS'][$i]['CSS_ICON'] = $arPosition[$arItem['NAME']][2];
	}
}
*/

$this->IncludeComponentTemplate();
?>