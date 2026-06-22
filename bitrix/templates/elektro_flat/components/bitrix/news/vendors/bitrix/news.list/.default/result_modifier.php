<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

foreach($arResult["ITEMS"] as $key => $arItem) {
	$picture = null;

	if (is_array($arItem["PREVIEW_PICTURE"])) {
		$picture = vilmedResizePicture($arItem["PREVIEW_PICTURE"], 69, 24);
	}

	if ($picture === null && is_array($arItem["DETAIL_PICTURE"])) {
		$picture = vilmedResizePicture($arItem["DETAIL_PICTURE"], 69, 24);
	}

	if ($picture !== null) {
		$arResult["ITEMS"][$key]["PREVIEW_PICTURE"] = $picture;
		continue;
	}

	if (!is_array($arItem["PREVIEW_PICTURE"]) || empty($arItem["PREVIEW_PICTURE"]["SRC"])) {
		unset($arResult["ITEMS"][$key]["PREVIEW_PICTURE"]);
	}
}?>
