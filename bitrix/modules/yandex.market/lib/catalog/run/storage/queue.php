<?php
namespace Yandex\Market\Catalog\Run\Storage;

use Bitrix\Main;
use Yandex\Market\Reference;
use Yandex\Market\Reference\Storage\Field;
use Yandex\Market\Catalog;

class QueueTable extends Reference\Storage\Table
{
    const STATUS_WAIT = 'W';
    const STATUS_SUCCESS = 'S';
    const STATUS_MISSING = 'M';
    const STATUS_ERROR = 'E';

    public static function getTableName()
    {
        return 'yamarket_catalog_run_queue';
    }

    public static function createIndexes(Main\DB\Connection $connection)
    {
        $tableName = static::getTableName();

        $connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'STATUS', 'PRIORITY' ]);
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
            new Main\Entity\EnumField('ENDPOINT', [
                'primary' => true,
                'values' => [
                    Catalog\Glossary::ENDPOINT_OFFER,
                    Catalog\Glossary::ENDPOINT_STOCKS,
                    Catalog\Glossary::ENDPOINT_PRICE,
                    Catalog\Glossary::ENDPOINT_TERMS,
                    Catalog\Glossary::ENDPOINT_ARCHIVE,
                ],
            ]),
            new Main\Entity\IntegerField('CAMPAIGN_ID', [
                'primary' => true,
            ]),
            new Main\Entity\EnumField('STATUS', [
                'required' => true,
                'values' => [
                    self::STATUS_WAIT,
                    self::STATUS_SUCCESS,
                    self::STATUS_ERROR,
                    self::STATUS_MISSING,
                ],
            ]),
            new Main\Entity\TextField('PAYLOAD', Field\JsonSerializer::getParameters() + [
                'required' => true,
	            'long' => true,
            ]),
            new Main\Entity\TextField('PREPARED', Field\JsonSerializer::getParameters(true) + [
                'nullable' => true,
	            'long' => true,
            ]),
            new Main\Entity\IntegerField('PRIORITY', [
                'required' => true,
            ]),
            new Reference\Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
                'required' => true,
            ]),

	        new Main\Entity\ReferenceField('ASSORTMENT', AssortmentTable::class, [
		        '=this.CATALOG_ID' => 'ref.CATALOG_ID',
		        '=this.SKU' => 'ref.SKU',
	        ]),
        ];
    }
}