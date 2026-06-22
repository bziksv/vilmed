<?php
namespace Yandex\Market\Migration\V282;

use Bitrix\Main;
use Yandex\Market;

class TradingSettingsSync
{
	public static function apply()
	{
		$tradingSetups = Market\Trading\Setup\Model::loadList([
			'filter' => [ '=ACTIVE' => true ],
		]);

		foreach ($tradingSetups as $tradingSetup)
		{
			$service = $tradingSetup->wakeupService();

			if (!($service instanceof Market\Trading\Service\Marketplace\Provider)) { continue; }
			if ($service->getOptions()->getValue('STORE_DATA') !== null) { continue; }

			Market\Trading\State\SettingsSync::process($tradingSetup->getId());

			if ($service->getOptions()->getValue('BUSINESS_ID') === null) { continue; }

			$nextExec = new Main\Type\DateTime();
			$nextExec->setTime(mt_rand(0, 10), mt_rand(0, 59));

			if ($nextExec->getTimestamp() <= time())
			{
				$nextExec->add('P1D');
			}

			Market\Trading\State\SettingsSync::register([
				'method' => 'process',
				'arguments' => [ (int)$tradingSetup->getId() ],
				'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
				'next_exec' => ConvertTimeStamp($nextExec->getTimestamp(), 'FULL'),
			]);
		}
	}
}