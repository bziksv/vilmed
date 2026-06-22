<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;
use Yandex\Market\Config;
use Yandex\Market\Ui\Trading as UiTrading;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui\Access;

/** @var CMain $APPLICATION */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

Loc::loadMessages(__FILE__);

if (!Main\Loader::includeModule('yandex.market'))
{
	\CAdminMessage::ShowMessage('Module yandex.market required');
}
else if (!Access::isReadAllowed())
{
	\CAdminMessage::ShowMessage('Access denied');
}
else
{
	$request = Main\Application::getInstance()->getContext()->getRequest();
	$businessId = UiTrading\Menu::extractBusinessId($request);
	$baseQuery = [ 'lang' => LANGUAGE_ID ] + UiTrading\Menu::baseQuery($businessId);

	$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
		'TITLE' => Config::getLang('TRADING_ADD_TITLE'),
		'BTN_SAVE' => Config::getLang('TRADING_ADD_BTN_ADD'),
		'FORM_ID' => 'YANDEX_MARKET_ADMIN_TRADING_ADD',
		'ALLOW_SAVE' => Access::isWriteAllowed(),
		'LIST_URL' => Admin\Path::getModuleUrl('trading_list', $baseQuery),
		'SAVE_URL' => Admin\Path::getModuleUrl('trading_edit', $baseQuery) . '&connectCampaign=#ID#',
		'PROVIDER' => Market\Component\TradingSetup\EditForm::class,
		'BUSINESS_ID' => $businessId,
		'CONTEXT_MENU' => [
			[
				'ICON' => 'btn_list',
				'LINK' => Admin\Path::getModuleUrl('trading_list', $baseQuery),
				'TEXT' => Config::getLang('TRADING_ADD_CONTEXT_MENU_LIST')
			]
		],
		'TABS' => [
			[ 'name' => Config::getLang('TRADING_ADD_TAB_COMMON') ],
		],
		'BUTTONS' => [
			[ 'BEHAVIOR' => 'save' ],
		],
	]);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
