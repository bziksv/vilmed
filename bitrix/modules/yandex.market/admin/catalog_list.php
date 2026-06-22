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
else if (!Market\Ui\Access::isProcessExportAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ACCESS_DENIED')
	]);
}
else
{
	$businessId = Market\Ui\Trading\Menu::extractBusinessId();
	$baseQuery =
		[ 'lang' => LANGUAGE_ID ]
		+ Market\Ui\Trading\Menu::baseQuery($businessId);

	$APPLICATION->IncludeComponent('yandex.market:admin.grid.list', '', [
		'GRID_ID' => 'YANDEX_MARKET_ADMIN_CATALOG_LIST',
		'ALLOW_SAVE' => Market\Ui\Access::isWriteAllowed(),
		'PROVIDER' => Market\Component\Catalog\GridList::class,
		'MODEL_CLASS_NAME' => Market\Catalog\Setup\Model::class,
		'EDIT_URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_edit', $baseQuery) . '&id=#ID#',
		'ADD_URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_edit', $baseQuery),
		'EXPORT_URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_run', $baseQuery),
		'TITLE' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_PAGE_TITLE'),
		'NAV_TITLE' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_NAV_TITLE'),
		'BUSINESS_ID' => $businessId,
		'LIST_FIELDS' => [
			'ID',
			'BUSINESS',
            'AUTOUPDATE',
            'REFRESH_PERIOD',
            'REFRESH_TIME',
		],
        'DEFAULT_LIST_FIELDS' => [
            'BUSINESS',
            'AUTOUPDATE',
            'REFRESH_PERIOD',
        ],
 		'CONTEXT_MENU' => [
			[
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_BUTTON_ADD'),
				'LINK' => Market\Ui\Admin\Path::getModuleUrl('catalog_edit', $baseQuery),
				'ICON' => 'btn_new'
			],
		],
		'ROW_ACTIONS' => [
			'RUN' => [
				'URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_run', $baseQuery) . '&id=#ID#',
				'ICON' => 'unpack',
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_RUN')
			],
			'EDIT' => [
				'URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_edit', $baseQuery) . '&id=#ID#',
				'ICON' => 'edit',
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_EDIT'),
				'DEFAULT' => true
			],
			'COPY' => [
				'URL' => Market\Ui\Admin\Path::getModuleUrl('catalog_edit', $baseQuery) . '&id=#ID#&copy=Y',
				'ICON' => 'copy',
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_COPY')
			],
			'DELETE' => [
				'ICON' => 'delete',
				'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_DELETE'),
				'CONFIRM' => 'Y',
				'CONFIRM_MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_DELETE_CONFIRM')
			]
		],
		'GROUP_ACTIONS' => [
			'delete' => Loc::getMessage('YANDEX_MARKET_ADMIN_CATALOG_LIST_ROW_ACTION_DELETE')
		],
	]);

	Market\Ui\Checker\Announcement::show();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';