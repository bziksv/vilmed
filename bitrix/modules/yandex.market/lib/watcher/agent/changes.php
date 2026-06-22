<?php
namespace Yandex\Market\Watcher\Agent;

use Yandex\Market\Data\Run\Processor as RunProcessor;
use Yandex\Market\Glossary;
use Yandex\Market\Reference\Agent;
use Yandex\Market\Watcher\Track;

class Changes extends Agent\Base
{
	public static function getDefaultParams()
	{
		return [
			'interval' => 5,
			'sort' => 200, // more priority
		];
	}

	public static function schedule($service)
	{
		self::register([
			'method' => 'process',
			'arguments' => [ $service ],
		]);
	}

	public static function process()
	{
		$needRepeat = false;
		$hasReady = false;

		foreach (self::tracked() as $stamp)
		{
			$stampState = new Track\StampState($stamp['SERVICE'], $stamp['SETUP_ID']);
			$changes = Track\ChangesRepository::tasks($stampState);
			$lastChange = end($changes);

			if (empty($changes))
			{
				$hasReady = true;
				$stampState->shift();
				continue;
			}

			$needRepeat = true;
			$processor = Factory::processor('change', $stamp['SERVICE'], $stamp['SETUP_ID']);
			$interrupted = $processor->run(RunProcessor::ACTION_CHANGE, [
				'changes' => self::groupChangesByType($changes),
			]);

			if ($interrupted)
			{
				$stampState->interrupt($lastChange['ID']);
				break;
			}

			$hasReady = true;
			$stampState->shift($lastChange['ID']);
		}

		if ($hasReady)
		{
			Track\ChangesRepository::clearProcessed();
		}

		return $needRepeat;
	}

	private static function tracked()
	{
		$servicesSort = array_flip([
			Glossary::SERVICE_CATALOG,
			Glossary::SERVICE_SALES_BOOST,
			Glossary::SERVICE_EXPORT,
		]);

		$rows = Track\StampTable::getList([ 'select' => [ 'SERVICE', 'OFFSET', 'SETUP_ID' ] ])->fetchAll();

		usort($rows, static function(array $aRow, array $bRow) use ($servicesSort) {
			if ($aRow['OFFSET'] !== $bRow['OFFSET'])
			{
				return ($aRow['OFFSET'] < $bRow['OFFSET'] ? -1 : 1);
			}

			$aServiceSort = $servicesSort[$aRow['SERVICE']];
			$bServiceSort = $servicesSort[$bRow['SERVICE']];

			if ($aServiceSort !== $bServiceSort)
			{
				return ($aServiceSort < $bServiceSort ? -1 : 1);
			}

			return ($aRow['SETUP_ID'] < $bRow['SETUP_ID'] ? -1 : 1);
		});

		return $rows;
	}

	private static function groupChangesByType(array $changes)
	{
		$result = [];

		foreach ($changes as $change)
		{
			if (!isset($result[$change['ELEMENT_TYPE']]))
			{
				$result[$change['ELEMENT_TYPE']] = [];
			}

			$result[$change['ELEMENT_TYPE']][] = $change['ELEMENT_ID'];
		}

		return $result;
	}
}