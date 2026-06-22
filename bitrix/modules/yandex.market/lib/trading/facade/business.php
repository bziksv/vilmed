<?php
namespace Yandex\Market\Trading\Facade;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading;

class Business
{
	public static function synchronize()
	{
		self::applyBusinessSchemeMigration();
		self::synchronizeBusinesses();
		self::rebuildMenu();
	}

	private static function applyBusinessSchemeMigration()
	{
		$migration = new Market\Migration\V300\BusinessScheme();

		if ($migration->wasApplied()) { return; }

		$migration->apply();
	}

	private static function synchronizeBusinesses()
	{
		foreach (Trading\Business\Model::loadList() as $business)
		{
			try
			{
				$business->getCampaignRepository()->synchronize(true);
			}
			catch (Main\SystemException $exception)
			{
				$business->createLogger()->error($exception);
			}
		}
	}

	private static function rebuildMenu()
	{
		$compiler = new Market\Ui\Trading\MenuCompiler();
		$compiler->rebuild();
		$compiler->save();
	}
}