<?php

/** @global CMain $APPLICATION */
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Yandex\Market\Ui;

$accessLevel = (string)CMain::GetGroupRight('yandex.market');

if ($accessLevel <= 'D') { return false; }

Loc::loadMessages(__FILE__);

$businessesSerialized = CUserOptions::GetOption('yandex.market', 'menu_business', 'unknown');
$businesses = null;

if ($businessesSerialized !== 'unknown')
{
	/** @noinspection PhpMethodParametersCountMismatchInspection */
	$businesses = (int)PHP_VERSION >= 7
		? unserialize($businessesSerialized, [ 'allowed_classes' => false ])
		: unserialize($businessesSerialized);
}
else if (Loader::includeModule('yandex.market'))
{
	try
	{
		$businessesCompiler = new Ui\Trading\MenuCompiler();
		$businesses = $businessesCompiler->rebuild();

		$businessesCompiler->save();
	}
	catch (\Exception $exception)
	{
		trigger_error($exception->getMessage(), E_USER_WARNING);
	}
	/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
	catch (\Throwable $exception)
	{
		trigger_error($exception->getMessage(), E_USER_WARNING);
	}
}

if (!is_array($businesses)) { $businesses = []; }

$hasBusinesses = !empty($businesses);
$lastBusinessId = $hasBusinesses ? end($businesses)['ID'] : null;
$businessSort = 1010;

$yaMenu = array_merge([
	[
		'parent_menu' => 'global_menu_services',
		'section' => 'yamarket_marketplace',
		'sort' => 1005,
		'text' => Loc::getMessage('YANDEX_MARKET_MENU_CONNECT'),
		'title' => Loc::getMessage('YANDEX_MARKET_MENU_CONNECT'),
		'icon' => 'yamarket_promotion_icon',
		'url' => 'yamarket_trading_connect.php?lang='.LANGUAGE_ID,
		'more_url' => array_merge([
			'yamarket_trading_connect.php',
		], array_map(
			static function($connectKey) { return 'yamarket_trading_edit.php?connect=' . $connectKey; },
			isset($_SESSION['yamarket_connect']) && is_array($_SESSION['yamarket_connect']) ? array_keys($_SESSION['yamarket_connect']) : []
		), $hasBusinesses ? [] : [
			'yamarket_trading_setup.php',
			'yamarket_trading_list.php',
			'yamarket_trading_order_admin.php',
			'yamarket_trading_order_list.php',
			'yamarket_trading_shipment_list.php',
			'yamarket_trading_edit.php',
			'yamarket_catalog_edit.php',
			'yamarket_catalog_run.php',
			'yamarket_sales_boost_list.php',
			'yamarket_sales_boost_edit.php',
			'yamarket_sales_boost_run.php',
			'yamarket_sales_boost_bids.php',
			'yamarket_trading_log.php',
		]),
		'items_id' => 'menu_yamarket_connect',
		'rights' => 'PT',
	]
],
array_map(static function(array $business) use ($lastBusinessId, &$businessSort) {
	$isLast = ($lastBusinessId === $business['ID']);
	$businessQuery = http_build_query([
		'lang' => LANGUAGE_ID,
		'business' => $business['ID'],
	]);

	return [
		'parent_menu' => 'global_menu_services',
		'section' => 'yamarket_marketplace_' . $business['ID'],
		'sort' => $businessSort++,
		'text' => $business['NAME'],
		'title' => $business['NAME'],
		'icon' => 'yamarket_origin_icon',
		'items_id' => 'menu_yamarket_business_' . $business['ID'],
		'items' => array_map(static function(array $item) use ($isLast, $businessQuery) {
			if ($isLast || empty($item['more_url'])) { return $item; }

			$item['more_url'] = array_filter($item['more_url'], static function($url) use ($businessQuery) {
				return mb_strpos($url, $businessQuery) !== false;
			});

			return $item;
		}, [
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_ORDER_ADMIN'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_ORDER_ADMIN'),
				'url' => 'yamarket_trading_order_admin.php?' . $businessQuery,
				'more_url' => [
					'yamarket_trading_order_admin.php',
				],
				'rights' => 'PT',
				'hidden' => ($business['ID'] === 0 || empty($business['BEHAVIOR'])),
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_ORDER_LIST'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_ORDER_LIST'),
				'url' => 'yamarket_trading_order_list.php?'. $businessQuery,
				'rights' => 'PT',
				'hidden' => ($business['ID'] === 0 || empty($business['BEHAVIOR'])),
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_SHIPMENT_LIST'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_SHIPMENT_LIST'),
				'url' => 'yamarket_trading_shipment_list.php?'. $businessQuery,
				'rights' => 'PT',
				'hidden' => ($business['ID'] === 0 || !in_array('default', $business['BEHAVIOR'], true)),
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_SETTINGS'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_SETTINGS'),
				'url' => $business['BUSINESS_BEHAVIOR'] && !$business['CAMPAIGN_BEHAVIOR']
					? 'yamarket_trading_edit.php?' . $businessQuery
					: 'yamarket_trading_list.php?' . $businessQuery,
				'more_url' => [
					'yamarket_trading_list.php?' . $businessQuery,
					'yamarket_trading_setup.php?' . $businessQuery,
					'yamarket_trading_edit.php?' . $businessQuery,
					'yamarket_trading_list.php',
					'yamarket_trading_setup.php',
					'yamarket_trading_order_list.php',
					'yamarket_trading_shipment_list.php',
				],
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_CATALOG'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_CATALOG'),
				'url' => 'yamarket_catalog_list.php?' . $businessQuery,
				'more_url' => [
					'yamarket_catalog_list.php?' . $businessQuery,
					'yamarket_catalog_edit.php?' . $businessQuery,
					'yamarket_catalog_run.php?' . $businessQuery,
					'yamarket_catalog_list.php',
					'yamarket_catalog_edit.php',
					'yamarket_catalog_run.php',
				],
				'rights' => 'PE',
				'hidden' => ($business['ID'] > 0),
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_CATALOG'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_CATALOG'),
				'url' => $business['BUSINESS_BEHAVIOR']
					? 'yamarket_trading_edit.php?' . $businessQuery . '&YANDEX_MARKET_ADMIN_TRADING_EDIT_active_tab=tab_catalog'
					: 'yamarket_catalog_edit.php?' . $businessQuery,
				'more_url' => [
					'yamarket_catalog_list.php?' . $businessQuery,
					'yamarket_catalog_edit.php?' . $businessQuery,
					'yamarket_catalog_run.php?' . $businessQuery,
					'yamarket_catalog_list.php',
					'yamarket_catalog_edit.php',
					'yamarket_catalog_run.php',
				],
				'rights' => 'PE',
				'hidden' => ($business['ID'] === 0),
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_SALES_BOOST_SETUP'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_SALES_BOOST_SETUP'),
				'url' => 'yamarket_sales_boost_list.php?' . $businessQuery,
				'more_url' => [
					'yamarket_sales_boost_list.php?' . $businessQuery,
					'yamarket_sales_boost_edit.php?' . $businessQuery,
					'yamarket_sales_boost_run.php?' . $businessQuery,
					'yamarket_sales_boost_bids.php?' . $businessQuery,
					'yamarket_sales_boost_list.php',
					'yamarket_sales_boost_edit.php',
					'yamarket_sales_boost_run.php',
					'yamarket_sales_boost_bids.php',
				],
				'rights' => 'PT',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_EVENT'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_EVENT'),
				'url' => 'yamarket_trading_log.php?' . $businessQuery,
				'more_url' => [
					'yamarket_trading_log.php',
				],
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_HELP'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_HELP'),
				'url' => 'https://yandex.ru/support/marketplace-module-1c-bitrix/',
				'more_url' => [],
				'rights' => 'PT',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_FEEDBACK'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_FEEDBACK'),
				'url' => $business['ID'] > 0
					? sprintf('https://partner.market.yandex.ru/business/%s/support', (int)$business['ID'])
					: 'https://marketplace.1c-bitrix.ru/solutions/yandex.market/#tab-support-link',
				'rights' => 'PT',
			]
		]),
	];
}, $businesses),
[
	[
		'parent_menu' => 'global_menu_services',
		'section' => 'yamarket_origin',
		'sort' => 1050,
		'text' => Loc::getMessage('YANDEX_MARKET_MENU_ORIGIN_ROOT'),
		'title' => Loc::getMessage('YANDEX_MARKET_MENU_ORIGIN_ROOT'),
		'icon' => 'yamarket_assortment_icon',
		'items_id' => 'menu_yamarket',
		'items' => [
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_SETUP'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_SETUP'),
				'url' => 'yamarket_setup_list.php?lang=' . LANGUAGE_ID . '&find_group=0&set_filter=Y&apply_filter=Y',
				'more_url' => [
					'yamarket_setup_list.php',
					'yamarket_setup_edit.php',
					'yamarket_setup_group_edit.php',
					'yamarket_setup_run.php',
					'yamarket_migration.php',
					'yamarket_checker.php',
				],
				'rights' => 'PE',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_COLLECTION'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_COLLECTION'),
				'url' => 'yamarket_collection_list.php?lang='.LANGUAGE_ID,
				'more_url' => [
					'yamarket_collection_edit.php',
					'yamarket_collection_run.php',
					'yamarket_collection_result.php',
				],
				'rights' => 'PE',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_PROMO'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_PROMO'),
				'url' => 'yamarket_promo_list.php?lang='.LANGUAGE_ID,
				'more_url' => [
					'yamarket_promo_list.php',
					'yamarket_promo_edit.php',
					'yamarket_promo_run.php',
					'yamarket_promo_result.php',
				],
				'rights' => 'PE',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_LOG'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_LOG'),
				'url' => 'yamarket_log.php?lang='.LANGUAGE_ID,
				'more_url' => [
					'yamarket_log.php',
				],
				'rights' => 'PE',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_CONFIRMATION'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_CONFIRMATION'),
				'url' => 'yamarket_confirmation_list.php?lang='.LANGUAGE_ID,
				'more_url' => [
					'yamarket_confirmation_list.php',
					'yamarket_confirmation_edit.php',
				],
				'rights' => 'PE',
			],
			[
				'text' => Loc::getMessage('YANDEX_MARKET_MENU_HELP'),
				'title' => Loc::getMessage('YANDEX_MARKET_MENU_HELP'),
				'url' => 'https://yandex.ru/support/market-cms/',
				'more_url' => [],
				'rights' => 'PE',
			],
		]
	],
]);

// filter items by access rights

foreach ($yaMenu as $yaRootLevelKey => &$yaRootLevel)
{
	if (!empty($yaRootLevel['hidden']))
	{
		unset($yaMenu[$yaRootLevelKey]);
		continue;
	}

	if (isset($yaRootLevel['rights']))
	{
		if ($accessLevel[0] < $yaRootLevel['rights'][0])
		{
			$isMatchModuleRights = false;
		}
		else if ($accessLevel[0] > $yaRootLevel['rights'][0])
		{
			$isMatchModuleRights = true;
		}
		else
		{
			$isMatchModuleRights = ($accessLevel === $yaRootLevel['rights']);
		}

		if (!$isMatchModuleRights)
		{
			unset($yaMenu[$yaRootLevelKey]);
			continue;
		}
	}

	if (!isset($yaRootLevel['items'])) { continue; }

	foreach ($yaRootLevel['items'] as $yaItemKey => $yaItem)
	{
		// hidden

		if (!empty($yaItem['hidden']))
		{
			unset($yaRootLevel['items'][$yaItemKey]);
			continue;
		}

		// access

		$yaItemRights = isset($yaItem['rights']) ? $yaItem['rights'] : 'R';

		if ($accessLevel[0] < $yaItemRights[0])
		{
			$isMatchModuleRights = false;
		}
		else if ($accessLevel[0] > $yaItemRights[0])
		{
			$isMatchModuleRights = true;
		}
		else
		{
			$isMatchModuleRights = ($accessLevel === $yaItemRights);
		}

		if (!$isMatchModuleRights)
		{
			unset($yaRootLevel['items'][$yaItemKey]);
		}
	}

	if (empty($yaRootLevel['items']))
	{
		unset($yaMenu[$yaRootLevelKey]);
	}
}
unset($yaRootLevel);

return $yaMenu;