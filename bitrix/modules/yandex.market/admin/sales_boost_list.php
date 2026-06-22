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
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_REQUIRE_MODULE')
	]);
}
else if (!Market\Ui\Access::isProcessExportAllowed())
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ACCESS_DENIED')
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

	$APPLICATION->IncludeComponent(
		'yandex.market:admin.grid.list',
		'',
		[
			'GRID_ID' => 'YANDEX_MARKET_ADMIN_SALES_BOOST_LIST',
			'ALLOW_SAVE' => Market\Ui\Access::isWriteAllowed(),
			'PROVIDER' => Market\Component\SalesBoost\GridList::class,
			'MODEL_CLASS_NAME' => Market\SalesBoost\Setup\Model::class,
			'EDIT_URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_edit', $baseQuery) . '&id=#ID#',
			'ADD_URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_edit', $baseQuery),
			'EXPORT_URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_run', $baseQuery),
			'TITLE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_PAGE_TITLE'),
			'NAV_TITLE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_NAV_TITLE'),
			'BASE_URL' => $APPLICATION->GetCurPage() . '?' . http_build_query($baseQuery),
			'BUSINESS_ID' => $businessId,
			'LIST_FIELDS' => [
				'ID',
				'NAME',
				'ACTIVE',
				'SORT',
				'BUSINESS',
				'START_DATE',
				'FINISH_DATE',
			],
			'CONTEXT_MENU' => [
				[
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_BUTTON_ADD'),
					'LINK' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_edit', $baseQuery),
					'ICON' => 'btn_new'
				],
				[
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_BID_RESULT'),
					'LINK' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_bids', $baseQuery),
				],
			],
			'ROW_ACTIONS' => [
				'RUN' => [
					'URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_run', $baseQuery) . '&id=#ID#',
					'ICON' => 'unpack',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_RUN')
				],
				'EDIT' => [
					'URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_edit', $baseQuery) . '&id=#ID#',
					'ICON' => 'edit',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_EDIT'),
					'DEFAULT' => true
				],
				'ACTIVATE' => [
					'ACTION' => 'activate',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_ACTIVATE')
				],
				'DEACTIVATE' => [
					'ACTION' => 'deactivate',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DEACTIVATE'),
					'CONFIRM' => 'Y',
					'CONFIRM_MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DEACTIVATE_CONFIRM')
				],
				'COPY' => [
					'URL' => Market\Ui\Admin\Path::getModuleUrl('sales_boost_edit', $baseQuery) . '&id=#ID#&copy=Y',
					'ICON' => 'copy',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_COPY')
				],
				'DELETE' => [
					'ICON' => 'delete',
					'TEXT' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DELETE'),
					'CONFIRM' => 'Y',
					'CONFIRM_MESSAGE' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DELETE_CONFIRM')
				]
			],
			'GROUP_ACTIONS' => [
				'activate' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_ACTIVATE'),
				'deactivate' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DEACTIVATE'),
				'delete' => Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_ROW_ACTION_DELETE')
			]
		]
	);

	echo BeginNote('style="max-width: 600px;"');
	echo Loc::getMessage('YANDEX_MARKET_ADMIN_SALES_BOOST_LIST_NOTE');
	echo EndNote();

	Market\Ui\Checker\Announcement::show();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';