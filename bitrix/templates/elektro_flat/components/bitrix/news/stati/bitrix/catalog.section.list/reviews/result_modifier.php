<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arIBlock = CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "FIELDS");
$arParams["DISPLAY_IMG_WIDTH"] = $arIBlock["SECTION_PICTURE"]["DEFAULT_VALUE"]["WIDTH"] ? $arIBlock["SECTION_PICTURE"]["DEFAULT_VALUE"]["WIDTH"] : 50;
$arParams["DISPLAY_IMG_HEIGHT"] = $arIBlock["SECTION_PICTURE"]["DEFAULT_VALUE"]["HEIGHT"] ? $arIBlock["SECTION_PICTURE"]["DEFAULT_VALUE"]["HEIGHT"] : 50;

foreach($arResult["SECTIONS"] as $key => $arSection) {
	if(is_array($arSection["PICTURE"])) {
		$picture = vilmedResizePicture(
			$arSection["PICTURE"],
			(int)$arParams["DISPLAY_IMG_WIDTH"],
			(int)$arParams["DISPLAY_IMG_HEIGHT"]
		);
		if ($picture !== null) {
			$arResult["SECTIONS"][$key]["PICTURE"] = $picture;
		}
	}
}?>
