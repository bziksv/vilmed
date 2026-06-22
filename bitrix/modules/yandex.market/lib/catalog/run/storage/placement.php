<?php
namespace Yandex\Market\Catalog\Run\Storage;

use Bitrix\Main;
use Yandex\Market\Reference;

class PlacementTable extends Reference\Storage\Table
{
    const STATUS_PUBLISHED = 'P';
    const STATUS_ARCHIVED = 'A';

    public static function getTableName()
    {
        return 'yamarket_catalog_run_placement';
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
            new Main\Entity\IntegerField('CAMPAIGN_ID', [
                'primary' => true,
            ]),
            new Main\Entity\EnumField('STATUS', [
                'required' => true,
                'values' => [
                    static::STATUS_PUBLISHED,
                    static::STATUS_ARCHIVED,
                ],
            ]),

            new Main\Entity\ReferenceField('ASSORTMENT', AssortmentTable::class, [
                '=this.CATALOG_ID' => 'ref.CATALOG_ID',
                '=this.SKU' => 'ref.SKU',
            ]),
        ];
    }
}