<?php
namespace Yandex\Market\Catalog\Run\Storage;

use Bitrix\Main;
use Yandex\Market\Reference;

class AssortmentTable extends Reference\Storage\Table
{
	const STATUS_UNKNOWN = 'U';
	const STATUS_PLACED = 'P';
	const STATUS_MISSING = 'M';

	public static function getTableName()
	{
		return 'yamarket_catalog_run_assortment';
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'ELEMENT_ID' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_2', [ 'STATUS' ]);
	}

	public static function getMap()
	{
		return [
            new Main\Entity\IntegerField('CATALOG_ID', [
                'primary' => true,
            ]),
            new Main\Entity\StringField('SKU', [
                'primary' => true,
                'validation' => function() {
                    return [ new Main\Entity\Validator\Length(1, 255) ];
                },
            ]),
			new Main\Entity\IntegerField('ELEMENT_ID', [
                'nullable' => true,
            ]),
			new Main\Entity\IntegerField('CATEGORY_ID', [
                'nullable' => true,
            ]),
			new Main\Entity\EnumField('STATUS', [
				'required' => true,
				'values' => [
					static::STATUS_UNKNOWN,
					static::STATUS_PLACED,
					static::STATUS_MISSING,
				],
			]),
			new Reference\Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
				'required' => true,
			]),
			new Reference\Storage\Field\CanonicalDateTime('CREATED_AT', [
				'required' => true,
			]),

            new Main\Entity\ReferenceField('OFFER', OfferTable::class, [
                '=this.CATALOG_ID' => 'ref.CATALOG_ID',
                '=this.ELEMENT_ID' => 'ref.ELEMENT_ID',
            ]),
		];
	}
}