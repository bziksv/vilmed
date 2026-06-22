<?php
namespace Yandex\Market\Migration;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;

class StorageFacade
{
	public static function addNewFields(Main\DB\Connection $connection, Main\Entity\Base $entity)
	{
		$tableName = $entity->getDBTableName();
		$dbFields = $connection->getTableFields($tableName);

		foreach ($entity->getFields() as $field)
		{
			$fieldName = $field->getName();

			if (!isset($dbFields[$fieldName]) && $field instanceof Main\Entity\ScalarField)
			{
				self::addNewField($connection, $tableName, $field);
			}
		}
	}

	public static function addNewField(Main\DB\Connection $connection, $tableName, Main\Entity\Field $field)
	{
		if (!($field instanceof Main\Entity\ScalarField))
		{
			throw new Main\ArgumentException(sprintf('field must be instance of %s', Main\Entity\ScalarField::class));
		}

		$sqlHelper = $connection->getSqlHelper();

		/** @var Main\ORM\Fields\ScalarField $field */
		$connection->queryExecute(sprintf(
			'ALTER TABLE %s ADD COLUMN %s %s',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote($field->getColumnName()),
			$sqlHelper->getColumnTypeByField($field)
		));
		$connection->clearCaches();
	}

	public static function dropPrimary(Main\DB\Connection $connection, Main\Entity\Base $entity, array $columns = null)
	{
		$tableName = $entity->getDBTableName();

		if ($columns !== null && !$connection->isIndexExists($tableName, $columns)) { return; }

		$connection->queryExecute(sprintf(
			'ALTER TABLE %s DROP PRIMARY KEY',
			$connection->getSqlHelper()->quote($tableName)
		));
	}

	public static function createPrimary(Main\DB\Connection $connection, Main\Entity\Base $entity)
	{
		$sqlHelper = $connection->getSqlHelper();

		$connection->queryExecute(sprintf(
			'ALTER TABLE %s ADD PRIMARY KEY(%s)',
			$sqlHelper->quote($entity->getDBTableName()),
			implode(', ', array_map(static function($name) use ($entity, $sqlHelper) {
				/** @var Main\Entity\ScalarField $field */
				$field = $entity->getField($name);
				Assert::isSubclassOf($field, Main\Entity\ScalarField::class);

				return $sqlHelper->quote($field->getColumnName());
			}, $entity->getPrimaryArray()))
		));
	}

	public static function dropIndexes(Main\DB\Connection $connection, Main\Entity\Base $entity, $indexes)
	{
		$tableName = $entity->getDBTableName();
		$sqlHelper = $connection->getSqlHelper();

		foreach ($indexes as $index)
		{
			try
			{
				$connection->queryExecute(sprintf(
					'DROP INDEX %s ON %s',
					$sqlHelper->quote($index),
					$sqlHelper->quote($tableName)
				));
			}
			catch (Main\DB\SqlQueryException $exception)
			{
				// not exists
			}
		}
	}

	public static function renameColumn(Main\DB\Connection $connection, Main\Entity\Base $entity, $oldName, $fieldName)
	{
		$field = $entity->getField($fieldName);

		if (!($field instanceof Main\Entity\ScalarField))
		{
			throw new Main\ArgumentException(sprintf('field must be instance of %s', Main\Entity\ScalarField::class));
		}

		$tableName = $entity->getDBTableName();
		$sqlHelper = $connection->getSqlHelper();

		$connection->queryExecute(sprintf(
			'ALTER TABLE %s CHANGE COLUMN %s %s %s',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote($oldName),
			$sqlHelper->quote($field->getColumnName()),
			$sqlHelper->getColumnTypeByField($field)
		));
		$connection->clearCaches();
	}

	public static function updateFieldsLength(Main\DB\Connection $connection, Main\Entity\Base $entity, array $limitFields = null)
	{
		$sqlHelper = $connection->getSqlHelper();
		$tableName = $entity->getDBTableName();
		$storedTypes = static::getTableColumnTypes($connection, $entity);

		/** @var Main\ORM\Fields\ScalarField $field */
		foreach ($entity->getScalarFields() as $fieldName => $field)
		{
			if ($limitFields !== null && !in_array($fieldName, $limitFields, true)) { continue; }

			$columnName = $field->getColumnName();

			if (!isset($storedTypes[$columnName])) { continue; }

			$fieldType = $sqlHelper->getColumnTypeByField($field);
			$storedType = $storedTypes[$columnName];
			$fieldTypeLength = static::getColumnTypeLength($fieldType);
			$storedTypeLength = static::getColumnTypeLength($storedType);

			if ($fieldTypeLength === null || $storedTypeLength === null || $fieldTypeLength <= $storedTypeLength) { continue; }

			$connection->queryExecute(sprintf(
				'ALTER TABLE %s MODIFY COLUMN %s %s',
				$sqlHelper->quote($tableName),
				$sqlHelper->quote($columnName),
				$fieldType
			));
		}
	}

	public static function getTableColumnTypes(Main\DB\Connection $connection, Main\Entity\Base $entity)
	{
		$result = [];
		$sqlHelper = $connection->getSqlHelper();
		$tableName = $entity->getDBTableName();
		$queryColumns = $connection->query(sprintf(
			'SHOW COLUMNS FROM %s',
			$sqlHelper->quote($tableName)
		));

		while ($column = $queryColumns->fetch())
		{
			$result[$column['Field']] = $column['Type'];
		}

		return $result;
	}

	protected static function getColumnTypeLength($type)
	{
		$result = null;

		if (preg_match('/\((\d+)/', $type, $matches))
		{
			$result = (int)$matches[1];
		}

		return $result;
	}
}