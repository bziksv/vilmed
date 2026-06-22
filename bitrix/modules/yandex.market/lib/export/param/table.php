<?php
namespace Yandex\Market\Export\Param;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Migration;

class Table extends Market\Reference\Storage\Table
{
	const ENTITY_TYPE_IBLOCK_LINK = 'iblock_link';
	const ENTITY_TYPE_CATALOG_SEGMENT = 'catalog_segment';
	const ENTITY_TYPE_CATALOG_CARD = 'catalog_card';

	public static function getTableName()
	{
		return 'yamarket_export_param';
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'ENTITY_TYPE', 'ENTITY_ID' ]);
	}

	public static function getUfId()
	{
		return 'YAMARKET_EXPORT_PARAM';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true,
			]),
			new Main\Entity\EnumField('ENTITY_TYPE', [
				'required' => true,
				'default_value' => static::ENTITY_TYPE_IBLOCK_LINK,
				'values' => [
					static::ENTITY_TYPE_IBLOCK_LINK,
					static::ENTITY_TYPE_CATALOG_SEGMENT,
					static::ENTITY_TYPE_CATALOG_CARD,
				],
			]),
			new Main\Entity\IntegerField('ENTITY_ID', [
				'required' => true,
			]),
			new Main\Entity\ReferenceField('IBLOCK_LINK', Market\Export\IblockLink\Table::class, [
				'=this.ENTITY_TYPE' => [ '?', static::ENTITY_TYPE_IBLOCK_LINK ],
				'=this.ENTITY_ID' => 'ref.ID',
			]),
			new Main\Entity\StringField('XML_TAG', [
				'required' => true,
			]),
			new Main\Entity\ReferenceField('PARAM_VALUE', Market\Export\ParamValue\Table::class, [
				'=this.ID' => 'ref.PARAM_ID',
			]),
			new Main\Entity\TextField(
				'SETTINGS',
				Market\Reference\Storage\Field\Serializer::getParameters()
			),
			new Main\Entity\IntegerField('PARENT_ID', [
				'default_value' => 0,
			]),
		];
	}

	public static function getReference($primary = null)
	{
		return [
			'PARAM_VALUE' => [
				'TABLE' => Market\Export\ParamValue\Table::class,
				'LINK_FIELD' => 'PARAM_ID',
				'LINK' => [
					'PARAM_ID' => $primary,
				],
			],
			'CHILDREN' => [
				'TABLE' => static::class,
				'LINK_FIELD' => 'PARENT_ID',
				'LINK' => [
					'PARENT_ID' => $primary,
				],
			],
		];
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();
		$exists = $connection->getTableFields($tableName);

		parent::migrate($connection);
		static::migrateSettingsType($connection, $exists);
		static::migrateParentId($connection, $exists);
		static::migrateEntityType($connection, $exists);
		static::migrateEntityId($connection, $exists);
	}

	protected static function migrateSettingsType(Main\DB\Connection $connection, array $exists)
	{
		if (isset($exists['SETTINGS'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'ALTER TABLE %s ADD COLUMN %s text NOT NULL',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('SETTINGS')
		));
	}

	protected static function migrateParentId(Main\DB\Connection $connection, array $exists)
	{
		if (isset($exists['PARENT_ID'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s=0',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('PARENT_ID')
		));
	}

	protected static function migrateEntityType(Main\DB\Connection $connection, array $exists)
	{
		if (isset($exists['ENTITY_TYPE'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s="%s"',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('ENTITY_TYPE'),
			$sqlHelper->forSql(static::ENTITY_TYPE_IBLOCK_LINK)
		));
	}

	protected static function migrateEntityId(Main\DB\Connection $connection, array $exists)
	{
		if (!isset($exists['IBLOCK_LINK_ID'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s=%s',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('ENTITY_ID'),
			$sqlHelper->quote('IBLOCK_LINK_ID')
		));

		Migration\StorageFacade::dropIndexes($connection, static::getEntity(), [ 'IX_' . $tableName . '_0' ]);
		$connection->dropColumn($tableName, 'IBLOCK_LINK_ID');

		$connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'ENTITY_TYPE', 'ENTITY_ID' ]);
	}

	public static function saveExtractReference(array &$data)
	{
		$result = parent::saveExtractReference($data);

		if (!empty($result['CHILDREN']) && is_array($result['CHILDREN']))
		{
			foreach ($result['CHILDREN'] as &$child)
			{
				$child += [
					'ENTITY_TYPE' => $data['ENTITY_TYPE'],
					'ENTITY_ID' => $data['ENTITY_ID'],
				];
			}
			unset($child);
		}

		return $result;
	}
}