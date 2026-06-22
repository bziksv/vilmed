<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arSections = array();

foreach($arResult["SECTIONS"] as $key => $arItem) {

	if ($arItem['UF_HIDDEN']) {
		unset($arResult["SECTIONS"][$key]);
		continue;
	}
}

foreach($arResult["SECTIONS"] as $key => $arSection):
	if($arSection["IBLOCK_SECTION_ID"] > 0):
		$arSections[$arSection["IBLOCK_SECTION_ID"]]["CHILDREN"][$arSection["ID"]] = $arSection;
	else:
		$arSection["CHILDREN"] = array();
		$arSections[$arSection["ID"]] = $arSection;
	endif;
endforeach;

$arResult["SECTIONS"] = $arSections;

foreach($arResult["SECTIONS"] as $key => $arSection):
	if(isset($arSection["CHILDREN"]) && count($arSection["CHILDREN"]) > 0):
		foreach($arSection["CHILDREN"] as $keyChild => $arChild):
			if(is_array($arChild["PICTURE"])):
				$picture = vilmedResizePicture(
					$arChild["PICTURE"],
					(int)$arParams["DISPLAY_IMG_WIDTH"],
					(int)$arParams["DISPLAY_IMG_HEIGHT"]
				);
				if ($picture !== null) {
					$arResult["SECTIONS"][$key]["CHILDREN"][$keyChild]["PICTURE"] = $picture;
				}
			endif;
		endforeach;
	endif;
endforeach;?>
