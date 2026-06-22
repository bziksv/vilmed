<?php

use Bitrix\Main\Localization\Loc;
use Yandex\Market;

/** @var CMain $APPLICATION */
/** @var array $arParams */

Market\Ui\Assets::loadPlugin('admin', 'css');
Market\Ui\Assets::loadPlugin('grain', 'css');

$APPLICATION->IncludeComponent('yandex.market:admin.grid.list', '', [
	'GRID_ID' => 'YANDEX_MARKET_ADMIN_TRADING_LOG',
	'PROVIDER' => Market\Component\TradingLog\GridList::class,
	'DATA_CLASS_NAME' => Market\Logger\Trading\Table::class,
	'TITLE' => Loc::getMessage('YANDEX_MARKET_CRM_ROUTER_EVENT_TITLE'),
	'LIST_FIELDS' => [
		'TIMESTAMP_X',
		'AUDIT',
		'LEVEL',
		'MESSAGE',
		'ENTITY',
		'SETUP',
		'BUSINESS',
		'CAMPAIGN_ID',
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
		'CAMPAIGN_ID',
		'DEBUG',
	],
	'FILTER_FIELDS' => [
		'TIMESTAMP_X',
		'AUDIT',
		'LEVEL',
		'MESSAGE',
		'ORDER_ID',
		'OFFER_ID',
		'BUSINESS',
		'CAMPAIGN_ID',
		'SETUP',
	],
	'DEFAULT_FILTER_FIELDS' => [
		'AUDIT',
		'LEVEL',
		'MESSAGE',
		'ORDER_ID',
		'OFFER_ID',
		'SETUP',
	],
	'DEFAULT_SORT' => [ 'ID' => 'DESC' ],
	'CONTEXT_MENU_EXCEL' => 'Y',
	'ROW_ACTIONS' => [
		'DELETE' => [
			'ICON' => 'delete',
			'TEXT' => Loc::getMessage('YANDEX_MARKET_CRM_ROUTER_EVENT_ACTION_DELETE'),
			'CONFIRM' => 'Y',
			'CONFIRM_MESSAGE' => Loc::getMessage('YANDEX_MARKET_CRM_ROUTER_EVENT_ACTION_DELETE_CONFIRM')
		]
	],
	'GROUP_ACTIONS' => [
		'delete' => Loc::getMessage('YANDEX_MARKET_CRM_ROUTER_EVENT_ACTION_DELETE'),
	],
	'ALLOW_BATCH' => 'Y',
]);