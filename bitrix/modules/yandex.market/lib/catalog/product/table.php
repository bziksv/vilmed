<?php
namespace Yandex\Market\Catalog\Product;

use Bitrix\Main\Entity;
use Bitrix\Main\DB;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Catalog;
use Yandex\Market\Export;
use Yandex\Market\Ui\UserField\Manager;

class Table extends Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_catalog_product';
	}

	public static function createIndexes(DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_0', [ 'SETUP_ID' ]);
	}

	public static function getMap()
	{
		return [
			new Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true,
			]),
			new Entity\IntegerField('SETUP_ID', [
				'required' => true,
			]),
			new Entity\ReferenceField('SETUP', Catalog\Setup\Table::class, [
				'=this.SETUP_ID' => 'ref.ID'
			]),
			new Entity\IntegerField('IBLOCK_ID', [
				'required' => true,
			]),

			// mapping

			new Entity\ReferenceField('PRICE_SEGMENT', Catalog\Segment\Table::class, [
				'=ref.SETUP_ID' => 'this.ID',
				'=ref.TYPE' => Catalog\Glossary::SEGMENT_PRICE,
			]),
			new Entity\ReferenceField('STOCK_SEGMENT', Catalog\Segment\Table::class, [
				'=ref.SETUP_ID' => 'this.ID',
				'=ref.TYPE' => Catalog\Glossary::SEGMENT_STOCKS,
			]),
			new Entity\ReferenceField('OFFER_SEGMENT', Catalog\Segment\Table::class, [
				'=ref.SETUP_ID' => 'this.ID',
				'=ref.TYPE' => Catalog\Glossary::SEGMENT_OFFER,
			]),
            new Entity\ReferenceField('CARD_SEGMENT', Catalog\Card\Table::class, [
                '=ref.SETUP_ID' => 'this.ID',
            ]),

			// filter

			new Entity\ReferenceField('FILTER', Export\Filter\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Export\Filter\Table::ENTITY_TYPE_CATALOG_PRODUCT ],
				'=ref.ENTITY_ID' => 'this.ID'
			]),
			new Entity\BooleanField('EXPORT_ALL', [
				'values' => [ Storage\Table::BOOLEAN_N, Storage\Table::BOOLEAN_Y ],
				'default_value' => Storage\Table::BOOLEAN_Y,
			]),
		];
	}

	public static function getMapDescription()
	{
		$result = parent::getMapDescription();
		$result['IBLOCK_ID']['HIDDEN'] = 'Y';
		$segments = [
			'PRICE_SEGMENT' => Catalog\Glossary::SEGMENT_PRICE,
			'STOCK_SEGMENT' => [ Catalog\Glossary::SEGMENT_STOCKS, [
				'GROUP_FLAT' => 'Y',
			]],
			'OFFER_SEGMENT' => Catalog\Glossary::SEGMENT_OFFER,
			'CARD_SEGMENT' => Catalog\Glossary::SEGMENT_CARD,
		];

		foreach ($segments as $name => $segmentConfig)
		{
			if (!isset($result[$name])) { continue; }

            $userType = $name === 'CARD_SEGMENT' ? 'catalogCard' : 'catalogSegment';

			list($type, $settings) = is_array($segmentConfig) ? $segmentConfig : [$segmentConfig, []];

			$result[$name]['USER_TYPE'] = Manager::getUserType($userType);
			$result[$name]['SETTINGS']['FACTORY'] = Catalog\Segment\Registry::factory($type);
			$result[$name]['SETTINGS'] += $settings;
		}

		return $result;
	}

	public static function getReference($primary = null)
	{
		return [
			'PRICE_SEGMENT' => [
				'TABLE' => Catalog\Segment\Table::class,
				'LINK_FIELD' => 'PRODUCT_ID',
				'LINK' => [
					'PRODUCT_ID' => $primary,
					'TYPE' => Catalog\Glossary::SEGMENT_PRICE,
				],
			],
			'STOCK_SEGMENT' => [
				'TABLE' => Catalog\Segment\Table::class,
				'LINK_FIELD' => 'PRODUCT_ID',
				'LINK' => [
					'PRODUCT_ID' => $primary,
					'TYPE' => Catalog\Glossary::SEGMENT_STOCKS,
				],
			],
			'OFFER_SEGMENT' => [
				'TABLE' => Catalog\Segment\Table::class,
				'LINK_FIELD' => 'PRODUCT_ID',
				'LINK' => [
					'PRODUCT_ID' => $primary,
					'TYPE' => Catalog\Glossary::SEGMENT_OFFER,
				],
			],
			'CARD_SEGMENT' => [
				'TABLE' => Catalog\Card\Table::class,
				'LINK_FIELD' => 'PRODUCT_ID',
				'LINK' => [
					'PRODUCT_ID' => $primary,
				],
			],
			'FILTER' => [
				'TABLE' => Export\Filter\Table::class,
				'LINK_FIELD' => 'ENTITY_ID',
				'LINK' => [
					'ENTITY_TYPE' => Export\Filter\Table::ENTITY_TYPE_CATALOG_PRODUCT,
					'ENTITY_ID' => $primary,
				],
				'ORDER' => [
					'SORT' => 'asc',
					'ID' => 'asc',
				],
			],
		];
	}
}
