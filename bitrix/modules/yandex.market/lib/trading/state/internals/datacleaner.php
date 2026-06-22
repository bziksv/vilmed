<?php

namespace Yandex\Market\Trading\State\Internals;

use Yandex\Market;
use Bitrix\Main;

class DataCleaner extends Market\Reference\Agent\Regular
{
	public static function getDefaultParams()
	{
		return [
			'interval' => 86400,
		];
	}

	public static function run()
	{
		$types = [
			'data' => [ DataTable::class, 'TIMESTAMP_X' ],
			'entity' => [ EntityTable::class, 'TIMESTAMP_X' ],
			'status' => [ StatusTable::class, 'TIMESTAMP_X' ],
			'push' => [ PushTable::class, 'TIMESTAMP_X' ],
			'procedure' => [ Market\Trading\Procedure\QueueTable::class, 'EXEC_DATE' ],
		];

		/** @var class-string<Market\Reference\Storage\Table> $dataClass */
		foreach ($types as $type => list($dataClass, $timestampField))
		{
			$days = static::getExpireDays($type);

			if ($days <= 0) { return; }

			$dataClass::deleteBatch([
				'filter' => [ '<=' . $timestampField => static::buildExpireDate($days) ],
			]);
		}
	}

	public static function getExpireDays($type)
	{
		$option = sprintf('trading_%s_expire_days', $type);

		return (int)Market\Config::getOption($option, 30);
	}

	protected static function buildExpireDate($days)
	{
		$result = new Main\Type\DateTime();
		$result->add('-P' . (int)$days . 'D');

		return $result;
	}
}