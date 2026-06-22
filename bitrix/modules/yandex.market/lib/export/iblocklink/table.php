<?php
namespace Yandex\Market\Export\IblockLink;

use Bitrix\Main;
use Yandex\Market;

class Table extends Market\Reference\Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_export_iblocklink';
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_0', [ 'SETUP_ID' ]);
	}

	public static function getUfId()
	{
		return 'YAMARKET_EXPORT_IBLOCKLINK';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true
			]),
			new Main\Entity\IntegerField('SETUP_ID', [
				'required' => true
			]),
			new Main\Entity\ReferenceField('SETUP', Market\Export\Setup\Table::class, [
				'=this.SETUP_ID' => 'ref.ID'
			]),
			new Main\Entity\IntegerField('IBLOCK_ID', [
				'required' => true
			]),
			new Main\Entity\StringField('SALES_NOTES'),
			new Main\Entity\BooleanField('EXPORT_ALL', [
				'values' => [ '0', '1' ],
				'default_value' => '1',
			]),
			new Main\Entity\ReferenceField('DELIVERY', Market\Export\Delivery\Table::class, [
				'=this.ID' => 'ref.ENTITY_ID',
				'=ref.ENTITY_TYPE' => [ '?', Market\Export\Delivery\Table::ENTITY_TYPE_IBLOCK_LINK ]
			]),
			new Main\Entity\ReferenceField('FILTER', Market\Export\Filter\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Market\Export\Filter\Table::ENTITY_TYPE_IBLOCK_LINK ],
				'=ref.ENTITY_ID' => 'this.ID'
			]),
			new Main\Entity\ReferenceField('PARAM', Market\Export\Param\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Market\Export\Param\Table::ENTITY_TYPE_IBLOCK_LINK ],
				'=ref.ENTITY_ID' => 'this.ID'
			]),
		];
	}

	public static function getReference($primary = null)
	{
		return [
			'FILTER' => [
				'TABLE' => Market\Export\Filter\Table::class,
				'LINK_FIELD' => 'ENTITY_ID',
				'LINK' => [
					'ENTITY_TYPE' => Market\Export\Filter\Table::ENTITY_TYPE_IBLOCK_LINK,
					'ENTITY_ID' => $primary
				],
				'ORDER' => [
					'SORT' => 'asc',
					'ID' => 'asc'
				],
			],
			'PARAM' => [
				'TABLE' => Market\Export\Param\Table::class,
				'LINK_FIELD' => 'ENTITY_ID',
				'LINK' => [
					'ENTITY_TYPE' => Market\Export\Param\Table::ENTITY_TYPE_IBLOCK_LINK,
					'ENTITY_ID' => $primary,
					'PARENT_ID' => 0,
				],
				'ORDER' => [
					'ID' => 'asc',
				],
			],
			'DELIVERY' => [
				'TABLE' => Market\Export\Delivery\Table::class,
				'LINK_FIELD' => 'ENTITY_ID',
				'LINK' => [
					'ENTITY_TYPE' => Market\Export\Delivery\Table::ENTITY_TYPE_IBLOCK_LINK,
					'ENTITY_ID' => $primary,
				]
			]
		];
	}

	public static function loadExternalReference($primaryList, $select = null, $isCopy = false)
	{
		$parts = [];

		if (empty($select) || in_array('PARAM', $select, true))
		{
			$parts[] = Market\Export\Param\Repository::loadByReference(
				Market\Export\Param\Table::ENTITY_TYPE_IBLOCK_LINK,
				$primaryList,
				$isCopy
			);

			if (empty($select))
			{
				$references = static::getReference($primaryList);
				$select = array_keys($references);
			}

			$select = array_diff($select, [ 'PARAM' ]);
		}

		$parts[] = parent::loadExternalReference($primaryList, $select, $isCopy);

		return Market\Utils\ArrayHelper::mergeList(...$parts);
	}
}