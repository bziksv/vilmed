<?php
namespace Yandex\Market\Catalog\Run\Storage;

use Bitrix\Main;
use Yandex\Market\Reference;

class HashTable extends Reference\Storage\Table
{
	const STATUS_SUCCESS = 'S';
	const STATUS_ERROR = 'E';

	const PART_COMMON = 'common';
    const HASH_LENGTH = 32;

	public static function getTableName()
	{
		return 'yamarket_catalog_run_hash';
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
            new Main\Entity\IntegerField('CAMPAIGN_ID', [
                'primary' => true,
            ]),
            new Main\Entity\StringField('ENDPOINT_KEY', [
                'primary' => true,
                'validation' => function() {
                    return [ new Main\Entity\Validator\Length(1, 20) ];
                },
            ]),
			new Main\Entity\EnumField('STATUS', [
				'required' => true,
				'values' => [
					static::STATUS_SUCCESS,
					static::STATUS_ERROR,
				],
			]),
			new Main\Entity\TextField('HASH', [
                'nullable' => true,
                'validation' => function() {
                    return [ new Main\Entity\Validator\Length(null, self::HASH_LENGTH) ];
                },
            ]),
		];
	}
}