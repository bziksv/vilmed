<?php
namespace Yandex\Market\Trading\Procedure;

use Yandex\Market;

class Agent extends Market\Reference\Agent\Base
{
	public static function getDefaultParams()
	{
		return [
			'interval' => 60,
			'sort' => 200,
		];
	}

	public static function repeat()
	{
		Market\Environment::restore();
		Market\Environment::makeUserPlaceholder();

		$repeater = new Repeater();

		$repeater->processQueue();
		$needRepeat = static::modifyRepeatPeriod($repeater);

		Market\Environment::reset();

		return $needRepeat;
	}

	protected static function modifyRepeatPeriod(Repeater $repeater)
	{
		global $pPERIOD;

		$nearestInterval = $repeater->getNearestQueueInterval();
		$result = false;

		if ($nearestInterval !== null)
		{
			$result = true;
			$pPERIOD = $nearestInterval;
		}

		return $result;
	}
}