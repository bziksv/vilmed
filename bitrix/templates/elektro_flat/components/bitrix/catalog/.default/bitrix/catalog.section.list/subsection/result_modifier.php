<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

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