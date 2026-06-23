<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

//JS_CORE//
CJSCore::Init(array('popup', 'ajax', 'fx'));

//LCP preload — first product image on catalog section pages (page 1)
global $APPLICATION;
$vilmedCurPage = $APPLICATION->GetCurPage(false);
$vilmedIsCatalogSection = (strpos($vilmedCurPage, '/catalog/') === 0);
$vilmedIsFirstPage = empty($arResult['NAV_RESULT']) || (int)$arResult['NAV_RESULT']->NavPageNomer <= 1;
if ($vilmedIsCatalogSection && $vilmedIsFirstPage && !empty($arResult['ITEMS'][0]['PREVIEW_PICTURE']['SRC'])) {
	$vilmedLcpPicture = $arResult['ITEMS'][0]['PREVIEW_PICTURE'];
	$vilmedLcpSrc = function_exists('vilmedPicturePreloadSrc')
		? vilmedPicturePreloadSrc($vilmedLcpPicture)
		: $vilmedLcpPicture['SRC'];
	if ($vilmedLcpSrc !== '') {
		$APPLICATION->AddHeadString(
			'<link rel="preload" as="image" href="' . htmlspecialcharsbx($vilmedLcpSrc) . '" fetchpriority="high">',
			true
		);
	}
}

//LAZY_LOAD_JSON_ANSWERS//
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
if($request->isAjaxRequest() && ($request->get('action') === 'showMore' || $request->get('action') === 'deferredLoad')) {
	$content = ob_get_contents();
	ob_end_clean();

	list(, $itemsContainer) = explode('<!-- items-container -->', $content);
	list(, $paginationContainer) = explode('<!-- pagination-container -->', $content);

	if($arParams['AJAX_MODE'] === 'Y') {
		$component->prepareLinks($paginationContainer);
	}

	$component::sendJsonAnswer(array(
		'items' => $itemsContainer,
		'pagination' => $paginationContainer
	));
}