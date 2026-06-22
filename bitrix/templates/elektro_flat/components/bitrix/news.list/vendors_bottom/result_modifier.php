<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$vendorPreviewSize = ['width' => 69, 'height' => 24];

foreach($arResult["ITEMS"] as $key => $arItem) {
	if(!is_array($arItem["PREVIEW_PICTURE"])) {
		continue;
	}

	$picture = vilmedResizePicture($arItem["PREVIEW_PICTURE"], $vendorPreviewSize['width'], $vendorPreviewSize['height']);
	if ($picture !== null) {
		$arResult["ITEMS"][$key]['PICTURE_PREVIEW'] = $picture;
	}
}?>