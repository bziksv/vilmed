<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

if(!CModule::IncludeModule("iblock"))
	return;

global $arSetting;

if (isProductDetail()) {
	$arSetting["CATALOG_VIEW"] = $arSetting["CATALOG_VIEW_PRODUCT"];
}
	
if($arSetting["CATALOG_VIEW"]["VALUE"] == "FOUR_LEVELS"){
	$arParams["DEPTH_LEVEL"] = "4";
}elseif ($arSetting["CATALOG_VIEW"]["VALUE"] == "THREE_LEVELS"){
	$arParams["DEPTH_LEVEL"] = "3";
}elseif ($arSetting["CATALOG_VIEW"]["VALUE"] == "TWO_LEVELS"){
	$arParams["DEPTH_LEVEL"] = "2";
}else{
	$arParams["DEPTH_LEVEL"] = "1";
}

$arParams["IBLOCK_ID"] = "24";
$arParams["CACHE_TIME"] = "36000000";

$arResult["SECTIONS"] = array();
$arResult["ELEMENT_LINKS"] = array();

$arOrder = array(
	"left_margin" => "asc",
);

$arFilter = array(
	"IBLOCK_ID" => $arParams["IBLOCK_ID"],
	"GLOBAL_ACTIVE" => "Y",
	"IBLOCK_ACTIVE" => "Y",
	"<="."DEPTH_LEVEL" => $arParams["DEPTH_LEVEL"],
);

$arSelect = array(
	"ID",
	"IBLOCK_ID",
	"LEFT_MARGIN",
	"RIGHT_MARGIN",
	"NAME",
	"PICTURE",
	"DEPTH_LEVEL",
	"SECTION_PAGE_URL",
	"UF_ICON",
	"UF_ONLY_ONE",
	"UF_ONE_TWO",
	"UF_HIDDEN_ONLY",
	"UF_NAME_MENU",
	"UF_HIDDEN"
);

$rsSections = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);

$arHiddenIds = [];
while($arSection = $rsSections->GetNext()) {

	if($arSection["UF_HIDDEN"] || $arSection['UF_HIDDEN_ONLY'] || $arSection['UF_ONLY_ONE']  || $arSection['UF_ONE_TWO']){

		$arFilter = [
			'IBLOCK_ID' => $arSection['IBLOCK_ID'],
			'>=LEFT_MARGIN' => $arSection['LEFT_MARGIN'],
			'<RIGHT_MARGIN' => $arSection['RIGHT_MARGIN'],
			'>=DEPTH_LEVEL' => $arSection['DEPTH_LEVEL']
		];

		if($arSection['UF_ONLY_ONE'] || $arSection['UF_ONE_TWO']){
			unset($arFilter['>=LEFT_MARGIN'], $arFilter['>=DEPTH_LEVEL']);

			$arFilter['>LEFT_MARGIN'] = $arSection['LEFT_MARGIN'];
			$arFilter['>DEPTH_LEVEL'] = $arSection['DEPTH_LEVEL'];

			if($arSection['UF_ONE_TWO'])
				$arFilter['>DEPTH_LEVEL'] = $arSection['DEPTH_LEVEL']+1;
		}

		$rsSect = CIBlockSection::GetList(array('left_margin' => 'asc'),$arFilter);
		while ($arSect = $rsSect->GetNext())
			$arHiddenIds[$arSect['ID']] = $arSect['ID'];
	}

	$arResult["SECTIONS"][] = array(
		"ID" => $arSection["ID"],
		"DEPTH_LEVEL" => $arSection["DEPTH_LEVEL"],
		"~NAME" => $arSection["~NAME"],
		"~SECTION_PAGE_URL" => $arSection["~SECTION_PAGE_URL"],
		"PICTURE" => $arSection["PICTURE"],
		"ICON" => $arSection["UF_ICON"],
		"NAME_MENU" => $arSection["UF_NAME_MENU"],
	);

	$arResult["ELEMENT_LINKS"][$arSection["ID"]] = array();
}

$aMenuLinksExt = array();
$menuIndex = 0;
$previousDepthLevel = 1;
foreach($arResult["SECTIONS"] as $arSection) {

	if (in_array($arSection['ID'], $arHiddenIds))
		continue;

	if($menuIndex > 0)
		$aMenuLinksExt[$menuIndex - 1][3]["IS_PARENT"] = $arSection["DEPTH_LEVEL"] > $previousDepthLevel;
		$previousDepthLevel = $arSection["DEPTH_LEVEL"];

	$arResult["ELEMENT_LINKS"][$arSection["ID"]][] = urldecode($arSection["~SECTION_PAGE_URL"]);

	$aMenuLinksExt[$menuIndex++] = array(
		($arSection['NAME_MENU']) ?: htmlspecialcharsbx($arSection["~NAME"]),
		$arSection["~SECTION_PAGE_URL"],
		$arResult["ELEMENT_LINKS"][$arSection["ID"]],
		array(
			"ID" => $arSection["ID"],
			"FROM_IBLOCK" => true,
			"IS_PARENT" => false,
			"DEPTH_LEVEL" => $arSection["DEPTH_LEVEL"],
			"PICTURE" => $arSection["PICTURE"],
			"ICON" => $arSection["ICON"],
		)
	);
}

$aMenuLinks = array_merge($aMenuLinks, $aMenuLinksExt);?>
