<?php
namespace Yandex\Market\Catalog\Segment;

use Bitrix\Main\Entity;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Export\Param;
use Yandex\Market\Catalog;
use Yandex\Market\Utils\ArrayHelper;

class Table extends Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_catalog_segment';
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
			new Entity\IntegerField('CAMPAIGN_ID', [
				'required' => true,
			]),
			new Entity\EnumField('TYPE', [
				'required' => true,
				'values' => [
					Catalog\Glossary::SEGMENT_PRICE,
					Catalog\Glossary::SEGMENT_STOCKS,
					Catalog\Glossary::SEGMENT_OFFER,
				],
			]),
			new Entity\ReferenceField('PARAM', Param\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Param\Table::ENTITY_TYPE_CATALOG_SEGMENT ],
				'=ref.ENTITY_ID' => 'this.ID',
			]),
		];
	}

	public static function getReference($primary = null)
	{
		return [
			'PARAM' => [
				'TABLE' => Param\Table::class,
				'LINK_FIELD' => 'ENTITY_ID',
				'LINK' => [
					'ENTITY_TYPE' => Param\Table::ENTITY_TYPE_CATALOG_SEGMENT,
					'ENTITY_ID' => $primary,
					'PARENT_ID' => 0,
				],
				'ORDER' => [
					'ID' => 'asc',
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
			$parts[] = Param\Repository::loadByReference(Param\Table::ENTITY_TYPE_CATALOG_SEGMENT, $primaryList, $isCopy);

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
