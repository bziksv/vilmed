<?php
namespace Yandex\Market\Migration\V283;

use Yandex\Market\Export\Run\Writer\FileIndex;
use Yandex\Market\Export\Run\Writer\IndexFacade;

class DropInvalidFileIndex
{
	public static function apply()
	{
		$queryIndexes = FileIndex\RegistryTable::getList([
			'select' => [ 'SETUP_ID' ],
		]);

		while ($index = $queryIndexes->fetch())
		{
			if (self::hasTags($index['SETUP_ID'])) { continue; }

			IndexFacade::remove($index['SETUP_ID']);
		}
	}

	private static function hasTags($setupId)
	{
		return (bool)FileIndex\PositionTable::getRow([
			'select' => [ 'SETUP_ID' ],
			'filter' => [ '=SETUP_ID' => $setupId ],
		]);
	}
}