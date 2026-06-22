<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\Extension;

/** @var string $templateFolder */
/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponent $component */
/** @var CBitrixComponentTemplate $this */

$polyfillName = Extension::registerCompatible('main.polyfill.core'); // old bitrix fallback

if ($arParams['DELAYED'] === 'Y')
{
	$loaderScripts = Extension::assets('@Ui.AssetsLoader');
	$loaderScripts = Extension::injectFileUrl($loaderScripts);

	list($loaderStart, $loaderFinish) = explode('#FN#', sprintf(
		'(window.BX || top.BX).loadScript(%s, () => {
            (window.BX || top.BX).YandexMarket.Ui.AssetsLoader.load(%s).then(#FN#);
        });',
		Main\Web\Json::encode($loaderScripts['js']),
		Main\Web\Json::encode(Extension::injectFileUrl([
			'js' => $templateFolder . '/bundle.js',
			'css' => $templateFolder . '/bundle.css',
			'rel' => [
				Extension::assets($polyfillName),
			],
		]))
	));
}
else
{
	CJSCore::Init($polyfillName);

	$this->addExternalCss($templateFolder . '/bundle.css');
	$this->addExternalJs($templateFolder . '/bundle.js');

	list($loaderStart, $loaderFinish) = explode('#FN#', 'setTimeout(#FN#);');
}

$elementId = (int)$arParams['ELEMENT_ID'];
$bootClass = 'ym-sku-status-' . (int)$arParams['IBLOCK_ID'];
$modifierClass = !empty($arParams['ALONE']) ? 'is--alone' : '';
$ratingTitle = Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_RATING_TITLE');

$html = <<<PANEL
    <div class="ym-sku-status {$bootClass} {$modifierClass}" data-id="{$elementId}" data-theme="{$arParams['THEME']}">
    	{$ratingTitle}:
    	<span class="ym-sku-status-loader"><span class="ym-sku-status-loader__dot">.</span></span>
	</div>
PANEL;

$optionsEncoded = Main\Web\Json::encode([
	'elementId' => $elementId,
	'transport' => [
		'url' => $component->getPath() . '/ajax.php',
		'limit' => $arResult['LIMIT'],
		'payload' => [
			'iblockId' => $arParams['IBLOCK_ID'],
		],
	],
	'admin' => defined('ADMIN_SECTION'),
	'theme' => $arParams['THEME'],
	'locale' => [
		'RATING_TITLE' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_RATING_TITLE'),
		'RATING_SUFFIX' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_RATING_SUFFIX'),
		'ERRORS' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_ERRORS'),
		'WARNINGS' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_WARNINGS'),
		'RECOMMENDATIONS' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_RECOMMENDATIONS'),
		'AND' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_AND'),
		'MORE' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_MORE'),
		'DETAILS' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_DETAILS'),
		'UNPLACED' => Loc::getMessage('YANDEX_MARKET_STATUS_COMPONENT_UNPLACED'),
	],
]);

/** @noinspection BadExpressionStatementJS */
/** @noinspection JSUnresolvedReference */
/** @noinspection JSVoidFunctionReturnValueUsed */
$html .= <<<SCRIPT
    <script>
		(window.BX || top.BX).ready(function() {
            {$loaderStart}function() {
				(window.BX || top.BX).YandexMarket.Admin.SkuStatusFactory.instance('.{$bootClass}', $optionsEncoded)
            }{$loaderFinish}
        });
    </script>
SCRIPT;

$component->arResult['HTML'] = $html;