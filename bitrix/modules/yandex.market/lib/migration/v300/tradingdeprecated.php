<?php
namespace Yandex\Market\Migration\V300;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Reference\Storage\Batch\UpdateBatch;
use Yandex\Market\Trading;
use Yandex\Market\Ui\UserField;

class TradingDeprecated
{
	public function apply()
	{
		$migrateMap = Trading\Service\Migration::getMap();

		$tradings = Trading\Setup\Model::loadList([
			'filter' => [
				'=ACTIVE' => Trading\Setup\Table::BOOLEAN_Y,
				'=TRADING_SERVICE' => array_keys($migrateMap),
			],
		]);

		foreach ($tradings as $trading)
		{
			$this->migrateTrading($trading, $migrateMap);
		}
	}

	private function migrateTrading(Trading\Setup\Model $trading, array $migrateMap)
	{
		try
		{
			$serviceCode = $trading->getServiceCode();

			if (!isset($migrateMap[$serviceCode]))
			{
				$trading->setField('ACTIVE', UserField\BooleanType::VALUE_N);
				$trading->save();

				$this->deactivatePlatform($serviceCode);

				return;
			}

			$migrateService = Trading\Service\Manager::createProvider($migrateMap[$serviceCode]);

			$trading->migrate($migrateService);
			$trading->save();
		}
		catch (Main\SystemException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);
		}
	}

	private function deactivatePlatform($serviceCode)
	{
		if (!Main\Loader::includeModule('sale')) { return; }

		(new UpdateBatch(Sale\TradingPlatformTable::class))->run([
			'filter' => [ '=CODE' => "yamarket_{$serviceCode}" ]
		], [
			'ACTIVE' => 'N',
		]);
	}
}