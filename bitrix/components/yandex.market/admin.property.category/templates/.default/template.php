<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\Extension;
use Yandex\Market\Ui\UserField\Helper\Attributes;

/** @var string $templateFolder */
/** @var array $arParams */
/** @var CBitrixComponent $component */
/** @var \CBitrixComponentTemplate $this */

if ($arParams['DELAYED'] === 'Y')
{
    $loaderScripts = Extension::assets('@Ui.AssetsLoader');
    $loaderScripts = Extension::injectFileUrl($loaderScripts);
	$this->addExternalCss($templateFolder . '/bundle.css');

    list($loaderStart, $loaderFinish) = explode('#FN#', sprintf(
        '(window.BX || top.BX).loadScript(%s, () => {
            (window.BX || top.BX).YandexMarket.Ui.AssetsLoader.load(%s).then(#FN#);
        });',
        Main\Web\Json::encode($loaderScripts['js']),
        Main\Web\Json::encode(Extension::injectFileUrl([
            'js' => $templateFolder . '/bundle.js',
            'css' => $templateFolder . '/bundle.css',
            'rel' => [ Extension::assets('@lib.select2') ],
	        'variable' => 'BX.YandexMarket.Admin.Property.CategoryFactory',
        ]))
    ));
}
else
{
	Extension::load('@lib.select2');
	$this->addExternalCss($templateFolder . '/bundle.css');
	$this->addExternalJs($templateFolder . '/bundle.js');

    list($loaderStart, $loaderFinish) = explode('#FN#', 'setTimeout(#FN#);');
}

$bootClass = 'ym-category-property-' . $arParams['PROPERTY_ID'];
$themeClass = 'theme--' . $arParams['THEME'];
$skipInit = ($arParams['SKIP_INIT'] === 'Y');
$panelAttributes = Attributes::stringify(array_filter([
	'data-form-payload' => !empty($arParams['FORM_PAYLOAD']) ? $arParams['FORM_PAYLOAD'] : null,
]));

$categoryHtml = include __DIR__ . '/partials/category.php';
$parametersHtml = include __DIR__ . '/partials/parameters.php';

$html = <<<PANEL
    <div class="ym-category-panel {$bootClass} {$themeClass}" {$panelAttributes}>
        {$categoryHtml}
        {$parametersHtml}
    </div>
PANEL;

if (!$skipInit)
{
    $sharedOptions = [
		'transport' => [
			'url' => $component->getPath() . '/ajax.php',
			'componentParameters' => array_intersect_key($arParams, [
				'PROPERTY_TYPE' => true,
				'PROPERTY_ID' => true,
				'PROPERTY_IBLOCK' => true,
			]),
		],
        'locale' => [
            'CATEGORY_PLACEHOLDER' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_PLACEHOLDER'),
            'CATEGORY_LOAD_ERROR' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_LOAD_ERROR'),
            'CATEGORY_EMPTY' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_EMPTY'),
	        'LOADING' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_LOADING'),
	        'PARAMETER_DEPRECATED' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_DEPRECATED'),
	        'PARAMETER_DELETE' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_DELETE'),
	        'EMPTY_PROPERTIES' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_EMPTY_PROPERTIES'),
	        'BOOLEAN_Y' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_BOOLEAN_Y'),
	        'BOOLEAN_N' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_BOOLEAN_N'),
	        'PARAMETERS_ADD_HINT' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETERS_ADD_HINT'),
        ],
    ];

	if (!empty($arParams['FORM_TYPE']))
	{
		$sharedOptions['form'] = [
			'type' => $arParams['FORM_TYPE'],
			'fields' => $arParams['FORM_FIELDS'],
		];
	}
	else if (!empty($arParams['API_KEY_FIELD']))
	{
		$sharedOptions['form'] = [
			'apiKeyField' => $arParams['API_KEY_FIELD'],
		];
	}

	$optionsEncoded = Main\Web\Json::encode($sharedOptions);

	/** @noinspection BadExpressionStatementJS */
	/** @noinspection JSUnresolvedReference */
	/** @noinspection JSVoidFunctionReturnValueUsed */
	$html .= <<<SCRIPT
        <script>
            (window.BX || top.BX).ready(function() {
                {$loaderStart}function() {
					new (window.BX || top.BX).YandexMarket.Admin.Property.CategoryFactory("{$bootClass}", {$optionsEncoded})
                }{$loaderFinish}
            });
        </script>
SCRIPT;
}

$component->arResult['HTML'] = $html;