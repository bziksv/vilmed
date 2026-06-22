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
        'MESSAGE' => Loc::getMessage('YANDEX_MARKET_SALES_BOOST_EDIT_REQUIRE_MODULE')
    ]);
}
else if (!Market\Ui\Access::isProcessTradingAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_SALES_BOOST_EDIT_ACCESS_DENIED')
	]);
}
else
{
	$request = Main\Application::getInstance()->getContext()->getRequest();
	$id = Market\Data\Number::castInteger($request->get('id'));
	$businessId = Market\Ui\Trading\Menu::extractBusinessId($request);
	$baseQuery =
		[ 'lang' => LANGUAGE_ID ]
		+ Market\Ui\Trading\Menu::baseQuery($businessId);

	$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
		'TITLE' => Market\Config::getLang('SALES_BOOST_EDIT_TITLE_EDIT'),
		'TITLE_ADD' => Market\Config::getLang('SALES_BOOST_EDIT_TITLE_ADD'),
		'BTN_SAVE' => Market\Config::getLang('SALES_BOOST_EDIT_BTN_SAVE'),
		'FORM_ID'   => 'YANDEX_MARKET_ADMIN_SALES_BOOST_EDIT',
		'FORM_BEHAVIOR' => 'steps',
		'ALLOW_SAVE' => Market\Ui\Access::isWriteAllowed(),
		'BUSINESS_ID' => $businessId,
		'PRIMARY' => $id,
		'COPY' => isset($_GET['copy']) && $_GET['copy'] === 'Y',
		'LIST_URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_list', $baseQuery),
        'SAVE_URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_run', $baseQuery) . '&id=#ID#',
		'PROVIDER' => Market\Component\SalesBoost\EditForm::class,
		'MODEL_CLASS_NAME' => Market\SalesBoost\Setup\Model::class,
		'USE_METRIKA' => 'Y',
		'CONTEXT_MENU' => [
			[
				'TEXT' => Market\Config::getLang('SALES_BOOST_EDIT_CONTEXT_MENU_LIST'),
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_list', $baseQuery),
				'ICON' => 'btn_list',
			],
			[
				'TEXT' => Market\Config::getLang('SALES_BOOST_EDIT_CONTEXT_MENU_LOG'),
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('trading_log', $baseQuery + [
					'find_audit' => Market\Logger\Trading\Audit::SALES_BOOST,
					'find_level' => 'error',
					'set_filter' => 'Y',
					'apply_filter' => 'Y',
				]),
			],
			[
				'TEXT' => Market\Config::getLang('SALES_BOOST_EDIT_CONTEXT_MENU_BID_RESULT'),
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_bids', $baseQuery),
			],
		],
		'TABS' => [
			[
				'name' => Market\Config::getLang('SALES_BOOST_EDIT_TAB_COMMON'),
				'fields' => [
					'ACTIVE',
					'NAME',
					'START_DATE',
					'FINISH_DATE',
					'SORT',
					'BUSINESS',
				],
			],
			[
				'name' => Market\Config::getLang('SALES_BOOST_EDIT_TAB_RULE'),
				'layout' => 'product-filter',
				'fields' => [
					'BID_FORMAT',
					'BID_DEFAULT',
					'BID_FIELD',
					'SALES_BOOST_PRODUCT.FILTER',
				],
			],
		],
		'PRODUCT_FILTER_FIELDS' => [
			'SALES_BOOST_PRODUCT.FILTER',
		],
	]);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
