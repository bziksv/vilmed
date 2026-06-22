<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market\Config;
use Yandex\Market\Component;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui\Access;
use Yandex\Market\Ui\Checker;

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
	$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
		'TITLE' => Config::getLang('TRADING_CONNECT_TITLE'),
		'BTN_SAVE' => Config::getLang('TRADING_CONNECT_SAVE'),
		'FORM_ID' => 'YANDEX_MARKET_ADMIN_TRADING_ADD',
		'ALLOW_SAVE' => Access::isWriteAllowed(),
		'EDIT_URL' => Admin\Path::getModuleUrl('trading_edit') . '&id=#ID#',
		'SAVE_URL' => Admin\Path::getModuleUrl('trading_edit') . '&connect=#ID#',
		'DISABLE_REQUIRED_HIGHLIGHT' => 'Y',
		'PROVIDER' => Component\TradingConnect\EditForm::class,
		'PROVIDER_TYPE' => 'TradingConnect',
		'TABS' => [
			[ 'name' => Config::getLang('TRADING_CONNECT_TAB_COMMON') ],
		],
		'BUTTONS' => [
			[ 'BEHAVIOR' => 'save' ],
		],
	]);

	Checker\Announcement::show();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
