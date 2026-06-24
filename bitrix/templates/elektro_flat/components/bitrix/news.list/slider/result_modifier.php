<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;

if(!isset($arParams['SLIDER_AUTOPLAY']) || $arParams['SLIDER_AUTOPLAY'] != 'N')
	$arParams['SLIDER_AUTOPLAY'] = 'Y';

if(!isset($arParams['SLIDER_DELAY']) || empty($arParams['SLIDER_DELAY']) || !is_numeric($arParams['SLIDER_DELAY']))
	$arParams['SLIDER_DELAY'] = 3000;
else
	$arParams['SLIDER_DELAY'] = intval($arParams['SLIDER_DELAY']);

if(!isset($arParams['SLIDER_ASPECT_RATIO']) || empty($arParams['SLIDER_ASPECT_RATIO']))
	$arParams['SLIDER_ASPECT_RATIO'] = 'DEFAULT';

$arSlideHight = array(
	'DEFAULT' => 304,
	'16_7' => 419,
	'16_9' => 538
);

$arParam['SLIDER_HEIGHT'] = $arSlideHight[$arParams['SLIDER_ASPECT_RATIO']];

$arResult['IN_VIDEO'] = false;

foreach($arResult["ITEMS"] as $key => $arItem) {
	$previewFile = null;
	if (!empty($arItem['PREVIEW_PICTURE'])) {
		$previewFile = is_array($arItem['PREVIEW_PICTURE'])
			? $arItem['PREVIEW_PICTURE']
			: CFile::GetFileArray((int)$arItem['PREVIEW_PICTURE']);
	}

	if (is_array($previewFile) && !empty($previewFile['SRC'])) {
		$arFileTmp = CFile::ResizeImageGet(
			$previewFile,
			array('width' => 958, 'height' => $arParam['SLIDER_HEIGHT']),
			BX_RESIZE_IMAGE_PROPORTIONAL,
			true
		);

		$picture = array(
			'SRC' => $arFileTmp['src'],
			'WIDTH' => (int)$arFileTmp['width'],
			'HEIGHT' => (int)$arFileTmp['height'],
		);

		if (function_exists('vilmedAttachWebp')) {
			$picture = vilmedAttachWebp($picture);
		}

		$arResult['ITEMS'][$key]['PICTURE_PREVIEW'] = $picture;

		if ($key === 0 && empty($arResult['LCP_PRELOAD_SRC'])) {
			$arResult['LCP_PRELOAD_SRC'] = function_exists('vilmedPicturePreloadSrc')
				? vilmedPicturePreloadSrc($picture)
				: $picture['SRC'];
		}
	}
	
	foreach($arItem['PROPERTIES'] as $keyProp => $arProp) {
		if(!empty($arProp['VALUE']) && $keyProp == 'CODE_YOUTUBE') {
			$arResult['IN_VIDEO'] = true;
			break;
		}
	}
}

$this->__component->SetResultCacheKeys(
	array(
		'IN_VIDEO',
		'LCP_PRELOAD_SRC',
	)
);