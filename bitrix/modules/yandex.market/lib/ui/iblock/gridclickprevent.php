<?php
namespace Yandex\Market\Ui\Iblock;

use Bitrix\Main\EventManager;

class GridClickPrevent
{
	private static $wasBind = false;
	private static $disabledColumns = [];

	public static function disableColumn($column)
	{
		self::bind();
		self::$disabledColumns[$column] = true;
	}

	private static function bind()
	{
		if (self::$wasBind) { return; }

		self::$wasBind = true;

		$eventManager = EventManager::getInstance();
		$eventManager->addEventHandler('main', 'onAdminListDisplay', [self::class, 'onAdminListDisplay']);
	}

	public static function onAdminListDisplay(\CAdminList $adminList)
	{
		foreach (self::$disabledColumns as $column => $dummy)
		{
			if (isset($adminList->aHeaders[$column]))
			{
				$adminList->aHeaders[$column]['prevent_default'] = false;
			}
		}
	}
}