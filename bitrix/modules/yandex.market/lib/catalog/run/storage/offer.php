<?php
namespace Yandex\Market\Catalog\Run\Storage;

use Bitrix\Main;
use Yandex\Market\Reference;

class OfferTable extends Reference\Storage\Table
{
	const STATUS_SUCCESS = 'S';
	const STATUS_DUPLICATE = 'C';
	const STATUS_ERROR = 'E';
	const STATUS_DELETE = 'D';

	public static function getTableName()
	{
		return 'yamarket_catalog_run_offer';
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'SKU' ]);
        $connection->createIndex($tableName, 'IX_' . $tableName . '_2', [ 'STATUS' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_3', [ 'TIMESTAMP_X' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_4', [ 'PARENT_ID' ]);
	}

	public static function getMap()
	{
		return [
            new Main\Entity\IntegerField('CATALOG_ID', [
                'primary' => true,
            ]),
			new Main\Entity\IntegerField('ELEMENT_ID', [
				'primary' => true,
			]),
			new Main\Entity\IntegerField('PARENT_ID', [
				'default_value' => 0,
			]),
			new Main\Entity\StringField('SKU', [
                'nullable' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 255) ];
				},
			]),
			new Main\Entity\EnumField('STATUS', [
				'required' => true,
				'values' => [
					static::STATUS_SUCCESS,
					static::STATUS_DUPLICATE,
                    static::STATUS_ERROR,
					static::STATUS_DELETE,
				],
			]),
			new Reference\Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
				'required' => true,
			]),

            new Main\Entity\ReferenceField('ASSORTMENT', AssortmentTable::class, [
                '=this.CATALOG_ID' => 'ref.CATALOG_ID',
                '=this.ELEMENT_ID' => 'ref.ELEMENT_ID',
            ]),
		];
	}

}