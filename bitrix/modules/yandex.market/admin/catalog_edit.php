<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

global $APPLICATION;

Loc::loadMessages(__FILE__);

if (!Main\Loader::includeModule('yandex.market'))
{
	\CAdminMessage::ShowMessage([
        'TYPE' => 'ERROR',
        'MESSAGE' => Loc::getMessage('YANDEX_MARKET_REQUIRE_MODULE')
    ]);
}
else if (!Market\Ui\Access::isProcessTradingAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ACCESS_DENIED')
	]);
}
else
{
	$request = Main\Application::getInstance()->getContext()->getRequest();
	$businessId = Market\Ui\Trading\Menu::extractBusinessId($request);
	$baseQuery =
		[ 'lang' => LANGUAGE_ID ]
		+ Market\Ui\Trading\Menu::baseQuery($businessId);
	$knownBusiness = Market\Ui\Trading\Menu::isKnown($businessId);
	$id = $request->get('id');

	if ($id === null && $knownBusiness)
	{
		$existCatalog = Market\Catalog\Setup\Table::getRow([
			'filter' => [ '=BUSINESS_ID' => $businessId ],
			'select' => [ 'ID' ],
		]);

		$id = $existCatalog !== null ? (int)$existCatalog['ID'] : null;
	}

	if ($knownBusiness)
	{
		foreach (Market\Ui\Trading\Menu::stored() as $menuBusiness)
		{
			if ((int)$menuBusiness['ID'] !== (int)$businessId) { continue; }

			if ($menuBusiness['BUSINESS_BEHAVIOR'])
			{
				LocalRedirect(Market\Ui\Admin\Path::getModuleUrl('trading_edit', $baseQuery + [
					'YANDEX_MARKET_ADMIN_TRADING_EDIT_active_tab' => 'tab_catalog',
				]));
			}

			break;
		}
	}

	$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
		'TITLE' => Market\Config::getLang('CATALOG_EDIT_TITLE_EDIT'),
		'TITLE_ADD' => Market\Config::getLang('CATALOG_EDIT_TITLE_ADD'),
		'BTN_SAVE' => Market\Config::getLang('CATALOG_EDIT_BTN_SAVE'),
		'FORM_ID'   => 'YANDEX_MARKET_ADMIN_CATALOG_EDIT',
		'FORM_BEHAVIOR' => 'steps',
		'ALLOW_SAVE' => Market\Ui\Access::isWriteAllowed(),
		'PRIMARY' => $id,
		'COPY' => !$knownBusiness && isset($_GET['copy']) && $_GET['copy'] === 'Y',
		'LIST_URL' => $knownBusiness
			? Market\Ui\Admin\Path::getModuleUrl('trading_list', $baseQuery)
			: Market\Ui\Admin\Path::getModuleUrl('catalog_list', $baseQuery),
        'SAVE_URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_run', $baseQuery) . '&id=#ID#',
		'PROVIDER' => Market\Component\Catalog\EditForm::class,
		'BUSINESS_ID' => $businessId,
		'CONTEXT_MENU' => array_filter([
			[
				'ICON' => 'btn_list',
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('catalog_list', $baseQuery),
				'TEXT' => Market\Config::getLang('CATALOG_EDIT_CONTEXT_MENU_LIST')
			],
			[
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('catalog_run', $baseQuery + [ 'id' => $id ]),
				'TEXT' => Market\Config::getLang('CATALOG_EDIT_CONTEXT_MENU_RUN'),
			],
			$id > 0 ? [
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query([ 'postAction' => 'delete', 'sessid' => bitrix_sessid() ]),
					[ 'postAction', 'sessid' ]
				),
				'TEXT' => Market\Config::getLang('CATALOG_EDIT_CONTEXT_MENU_DELETE'),
			] : null,
		]),
		'TABS' => [
			[
				'name' => Market\Config::getLang('CATALOG_EDIT_TAB_COMMON'),
				'fields' => [
					'BUSINESS',
					'LOG_LEVEL',
                    'AUTOUPDATE',
                    'REFRESH_PERIOD',
                    'REFRESH_TIME',
				],
			],
			[
				'name' => Market\Config::getLang('CATALOG_EDIT_TAB_PARAM'),
				'fields' => [
					'PRODUCT.IBLOCK_ID',
					'PRICE_ENABLE',
					'PRODUCT.PRICE_SEGMENT',
					'STOCK_ENABLE',
					'PRODUCT.STOCK_SEGMENT',
					'OFFER_ENABLE',
					'PRODUCT.OFFER_SEGMENT',
					'CARD_ENABLE',
					'PRODUCT.CARD_SEGMENT',
				]
			],
			[
				'name' => Market\Config::getLang('CATALOG_EDIT_TAB_FILTER'),
				'layout' => 'product-filter',
				'fields' => [
					'PRODUCT.FILTER',
					'PRODUCT.EXPORT_ALL',
				],
			],
		],
		'PRODUCT_FILTER_FIELDS' => [
			'PRODUCT.FILTER',
			'PRODUCT.EXPORT_ALL',
		],
		'CATALOG_SEGMENT_FIELDS' => [
			'PRODUCT.PRICE_SEGMENT',
			'PRODUCT.STOCK_SEGMENT',
			'PRODUCT.OFFER_SEGMENT',
			'PRODUCT.CARD_SEGMENT',
		],
	]);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
