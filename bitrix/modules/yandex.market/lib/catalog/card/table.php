<?php
namespace Yandex\Market\Catalog\Card;

use Bitrix\Main\Entity;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Export\Param;
use Yandex\Market\Export;
use Yandex\Market\Utils\ArrayHelper;

class Table extends Storage\Table
{
    public static function getTableName()
    {
        return 'yamarket_catalog_card';
    }

    public static function getMap()
    {
        return	[
            new Entity\IntegerField('ID', [
                'autocomplete' => true,
                'primary' => true
            ]),
            new Entity\IntegerField('PRODUCT_ID', [
                'required' => true
            ]),
            new Entity\IntegerField('CATEGORY_ID', [
               'required' => true,
               'default_value' => 0,
            ]),
            new Entity\ReferenceField('PARAM', Export\Param\Table::class, [
                '=ref.ENTITY_TYPE' => [ '?', Export\Param\Table::ENTITY_TYPE_CATALOG_CARD ],
                '=ref.ENTITY_ID' => 'this.ID',
            ]),
        ];
    }

    public static function getReference($primary = null)
    {
        return [
            'PARAM' => [
                'TABLE' => Export\Param\Table::class,
                'LINK_FIELD' => 'ENTITY_ID',
                'LINK' => [
                    'ENTITY_TYPE' => Export\Param\Table::ENTITY_TYPE_CATALOG_CARD,
                    'ENTITY_ID' => $primary,
                    'PARENT_ID' => 0,
                ],
            ],
        ];
    }

    public static function isValidData($data)
    {
        return !empty($data['PARAM']);
    }

	public static function loadExternalReference($primaryList, $select = null, $isCopy = false)
	{
		$parts = [];

		if (empty($select) || in_array('PARAM', $select, true))
		{
			$parts[] = Param\Repository::loadByReference(Param\Table::ENTITY_TYPE_CATALOG_CARD, $primaryList, $isCopy);

			if (empty($select))
			{
				$references = static::getReference($primaryList);
				$select = array_keys($references);
			}

			$select = array_diff($select, [ 'PARAM' ]);
		}

		$parts[] = parent::loadExternalReference($primaryList, $select, $isCopy);

		return ArrayHelper::mergeList(...$parts);
	}
}
