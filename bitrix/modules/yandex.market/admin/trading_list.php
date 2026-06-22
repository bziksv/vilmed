<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;

/** @var CMain $APPLICATION */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

Loc::loadMessages(__FILE__);

if (!Main\Loader::includeModule('yandex.market'))
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_TRADING_LIST_REQUIRE_MODULE')
	]);
}
else if (!Market\Ui\Access::isReadAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Market\Config::getLang('ADMIN_TRADING_LIST_ACCESS_DENIED')
	]);
}
else
{
	Market\Metrika::load();

	$request = Main\Application::getInstance()->getContext()->getRequest();
	$businessId = Market\Ui\Trading\Menu::extractBusinessId($request);
	$baseQuery =
		[ 'lang' => LANGUAGE_ID ]
		+ Market\Ui\Trading\Menu::baseQuery($businessId);

	$APPLICATION->IncludeComponent('yandex.market:admin.grid.list', '', [
		'GRID_ID' => 'YANDEX_MARKET_ADMIN_TRADING_LIST',
		'ALLOW_SAVE' => Market\Ui\Access::isWriteAllowed(),
		'PROVIDER' => Market\Component\TradingSetup\GridList::class,
		'MODEL_CLASS_NAME' => Market\Trading\Setup\Model::class,
		'EDIT_URL' => Market\Ui\Admin\Path::getModuleUrl('trading_edit', $baseQuery) . '&id=#ID#',
		'ADD_URL' => Market\Ui\Admin\Path::getModuleUrl('trading_edit', $baseQuery),
		'TITLE' => Market\Config::getLang('ADMIN_TRADING_LIST_PAGE_TITLE'),
		'NAV_TITLE' => Market\Config::getLang('ADMIN_TRADING_LIST_NAV_TITLE'),
		'BASE_URL' => $APPLICATION->GetCurPage() . '?' . http_build_query($baseQuery),
		'BUSINESS_ID' => $businessId,
		'LIST_FIELDS' => [
			'ID',
			'SERVICE',
			'BUSINESS',
			'CAMPAIGN',
			'SITE_ID',
			'ACTIVE',
		],
		'DEFAULT_LIST_FIELDS' => [
			'SERVICE',
			'SITE_ID',
			'ACTIVE',
		],
		'FILTER_FIELDS' => [
			'ID',
			'BUSINESS',
			'CAMPAIGN',
			'SITE_ID',
			'ACTIVE',
		],
		'CONTEXT_MENU' => [
			[
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_BUTTON_ADD'),
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('trading_setup', $baseQuery),
				'ICON' => 'btn_new',
			],
			[
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_BUTTON_REINSTALL'),
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query([ 'postAction' => 'reinstall' ]),
					[ 'postAction' ]
				),
			],
		],
		'ROW_ACTIONS' => [
			'EDIT' => [
				'URL' => Market\Ui\Admin\Path::getModuleUrl('trading_edit', $baseQuery) . '&id=#ID#',
				'ICON' => 'edit',
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_EDIT'),
				'DEFAULT' => true
			],
			'LOG' => [
				'URL' => Market\Ui\Admin\Path::getModuleUrl('trading_log', $baseQuery + [
					'set_filter' => 'Y',
					'apply_filter' => 'Y',
				]) . '&find_setup=#ID#',
				'ICON' => 'view',
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_LOG'),
				'DEFAULT' => true
			],
			'ACTIVATE' => [
				'ACTION' => 'activate',
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_ACTIVATE')
			],
			'DEACTIVATE' => [
				'ACTION' => 'deactivate',
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_DEACTIVATE'),
				'CONFIRM' => 'Y',
				'CONFIRM_MESSAGE' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_DEACTIVATE_CONFIRM')
			],
			'DELETE' => [
				'ICON' => 'delete',
				'TEXT' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_DELETE'),
				'CONFIRM' => 'Y',
				'CONFIRM_MESSAGE' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_DELETE_CONFIRM')
			],
		],
		'GROUP_ACTIONS' => [
			'activate' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_ACTIVATE'),
			'deactivate' => Market\Config::getLang('ADMIN_TRADING_LIST_ROW_ACTION_DEACTIVATE'),
		]
	]);

	Market\Ui\Checker\Announcement::show();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';