<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;

/** @var CMain $APPLICATION */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

$isPopup = (isset($_REQUEST['popup']) && $_REQUEST['popup'] === 'Y');

if ($isPopup)
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
}
else
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
}

Loc::loadMessages(__FILE__);

if (!Main\Loader::includeModule('yandex.market'))
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_REQUIRE_MODULE')
	]);
}
else if (!Market\Ui\Access::isReadAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_ACCESS_DENIED')
	]);
}
else
{
	Market\Metrika::load();

	$businessId = Market\Ui\Trading\Menu::extractBusinessId();
	$baseQuery =
		[ 'lang' => LANGUAGE_ID ]
		+ Market\Ui\Trading\Menu::baseQuery($businessId);

	$APPLICATION->IncludeComponent('yandex.market:admin.grid.list', '', [
		'GRID_ID' => 'YANDEX_MARKET_ADMIN_TRADING_LOG_' . (int)$businessId,
		'PROVIDER' => Market\Component\TradingLog\GridList::class,
		'DATA_CLASS_NAME' => Market\Logger\Trading\Table::class,
		'BUSINESS_ID' => $businessId,
		'TITLE' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_PAGE_TITLE'),
		'BASE_URL' => $APPLICATION->GetCurPage() . '?' . http_build_query($baseQuery),
		'LIST_FIELDS' => [
			'TIMESTAMP_X',
			'AUDIT',
			'LEVEL',
			'MESSAGE',
			'ENTITY',
			'SETUP',
			'BUSINESS',
			'CAMPAIGN',
			'URL',
			'DEBUG',
			'ORDER_ID',
			'OFFER_ID',
		],
		'DEFAULT_LIST_FIELDS' => [
			'TIMESTAMP_X',
			'AUDIT',
			'LEVEL',
			'MESSAGE',
			'ENTITY',
			'SETUP',
			'CAMPAIGN',
			'DEBUG',
		],
		'FILTER_FIELDS' => [
			'TIMESTAMP_X',
			'AUDIT',
			'LEVEL',
			'MESSAGE',
			'ORDER_ID',
			'OFFER_ID',
			'CAMPAIGN',
			'SETUP',
		],
		'DEFAULT_FILTER_FIELDS' => [
			'AUDIT',
			'LEVEL',
			'MESSAGE',
			'ORDER_ID',
			'OFFER_ID',
			'CAMPAIGN',
			'SETUP',
		],
		'DEFAULT_SORT' => [ 'ID' => 'DESC' ],
		'CONTEXT_MENU_EXCEL' => 'Y',
		'ROW_ACTIONS' => [
			'DELETE' => [
				'ICON' => 'delete',
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_ROW_ACTION_DELETE'),
				'CONFIRM' => 'Y',
				'CONFIRM_MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_ROW_ACTION_DELETE_CONFIRM')
			]
		],
		'GROUP_ACTIONS' => [
			'delete' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LOG_ROW_ACTION_DELETE')
		],
		'ALLOW_BATCH' => 'Y'
	]);

	Market\Ui\Checker\Announcement::show();
}

if ($isPopup)
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_popup_admin.php';
}
else
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
}