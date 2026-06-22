<?php
namespace Yandex\Market\Reference\Storage;

use Yandex\Market\Utils\FileIterator;

class Controller
{
	public static function createTable($classList = null)
	{
		/** @var Table $className */
		foreach (self::classList($classList) as $className)
		{
			if (is_subclass_of($className, TableDeprecated::class)) { continue; }

			$entity = $className::getEntity();
			$connection = $entity->getConnection();
			$tableName = $entity->getDBTableName();

			if ($connection->isTableExists($tableName))
			{
				$className::migrate($connection);
				$connection->clearCaches();
			}
			else
			{
				$entity->createDbTable();
				$className::createIndexes($connection);
			}
		}
	}

	public static function dropTable($classList = null)
	{
		$dropped = [];

		/** @var Table $className */
		foreach (self::classList($classList) as $className)
		{
			$tableName = $className::getTableName();

			if (isset($dropped[$tableName])) { continue; }

			$entity = $className::getEntity();
			$connection = $entity->getConnection();
			$tableName = $entity->getDBTableName();

			if ($connection->isTableExists($tableName))
			{
				$connection->dropTable($tableName);
			}

			$dropped[$tableName] = true;
		}
	}

	private static function classList($classList = null)
	{
		if ($classList !== null) { return $classList; }

		return (new FileIterator())->findClasses(Table::class, 'Table');
	}
}
