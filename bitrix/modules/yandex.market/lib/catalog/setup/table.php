<?php
namespace Yandex\Market\Catalog\Setup;

use Bitrix\Main\Entity;
use Yandex\Market\Logger;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Catalog;
use Yandex\Market\Trading;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Watcher;

class Table extends Storage\Table
{
	use Concerns\HasMessage;

	public static function getTableName()
	{
		return 'yamarket_catalog_setup';
	}

	public static function getMap()
	{
		return array_merge([
                new Entity\IntegerField('ID', [
                    'autocomplete' => true,
                    'primary' => true
                ]),
                new Entity\IntegerField('BUSINESS_ID', [
                    'required' => true,
	                'validation' => static function() {
		                return [
			                static function($value, $primary) {
								if (empty($value)) { return true; }

								$exists = static::getRow([
									'filter' => [ '=BUSINESS_ID' => $value, '!=ID' => $primary ],
									'select' => [ 'ID' ],
								]);

								if ($exists === null) { return true; }

				                return self::getMessage('BUSINESS_ID_DUPLICATE', [
									'#BUSINESS_ID#' => $value,
					                '#URL#' => Admin\Path::getModuleUrl('catalog_edit', [ 'id' => $exists['ID'] ]),
				                ]);
			                },
		                ];
	                },
                ]),
				new Entity\ExpressionField('NAME', '%s', 'BUSINESS.NAME'),
				new Entity\EnumField('LOG_LEVEL', [
					'default_value' => Logger\Level::INFO,
					'values' => [
						Logger\Level::EMERGENCY,
						Logger\Level::ERROR,
						Logger\Level::WARNING,
						Logger\Level::INFO,
						Logger\Level::NOTICE,
						Logger\Level::DEBUG,
					],
				]),

				new Entity\BooleanField('PRICE_ENABLE', [
					'values' => [ Storage\Table::BOOLEAN_N, Storage\Table::BOOLEAN_Y ],
					'default_value' => Storage\Table::BOOLEAN_Y,
				]),
				new Entity\BooleanField('STOCK_ENABLE', [
					'values' => [ Storage\Table::BOOLEAN_N, Storage\Table::BOOLEAN_Y ],
					'default_value' => Storage\Table::BOOLEAN_Y,
				]),
				new Entity\BooleanField('OFFER_ENABLE', [
					'values' => [ Storage\Table::BOOLEAN_N, Storage\Table::BOOLEAN_Y ],
					'default_value' => Storage\Table::BOOLEAN_Y,
				]),
				new Entity\BooleanField('CARD_ENABLE', [
					'values' => [ Storage\Table::BOOLEAN_N, Storage\Table::BOOLEAN_Y ],
					'default_value' => Storage\Table::BOOLEAN_Y,
				]),

                new Entity\ReferenceField('BUSINESS', Trading\Business\Table::class, [
                    '=this.BUSINESS_ID' => 'ref.ID',
                ]),

                new Entity\ReferenceField('PRODUCT', Catalog\Product\Table::class, [
                    '=this.ID' => 'ref.SETUP_ID',
                ]),
            ],
            Watcher\Setup\StorageSchedule::getFields(true, Watcher\Setup\StorageSchedule::ONE_HOUR)
        );
	}

	public static function getReference($primary = null)
	{
		return [
			'PRODUCT' => [
				'TABLE' => Catalog\Product\Table::class,
				'LINK_FIELD' => 'SETUP_ID',
				'LINK' => [
					'SETUP_ID' => $primary,
				],
			],
		];
	}

	public static function getMapDescription()
	{
		$fields = parent::getMapDescription();

		$fields['BUSINESS']['MANDATORY'] = 'Y';
		$fields['BUSINESS']['SETTINGS']['ALLOW_UNKNOWN'] = 'Y';

		$fields['LOG_LEVEL']['DESCRIPTION'] = self::getMessage('LOG_LEVEL_DESCRIPTION');
		$fields['LOG_LEVEL']['VALUES'] = array_map(static function(array $option) {
			$option['VALUE'] = Logger\Level::getTitle($option['ID']);
			return $option;
		}, $fields['LOG_LEVEL']['VALUES']);
		$fields['LOG_LEVEL']['SETTINGS']['ALLOW_NO_VALUE'] = 'N';

        $fields = Watcher\Setup\StorageSchedule::extendMapDescription($fields);

		return $fields;
	}

	public static function saveExtractReference(array &$data)
	{
		$result = parent::saveExtractReference($data);
		$data = array_diff_key($data, [
			'BUSINESS' => true,
		]);

		return $result;
	}

	public static function deleteReference($primary)
	{
		parent::deleteReference($primary);
		static::deleteReferenceTables($primary);
		static::deleteReferenceChanges($primary);
	}

	protected static function deleteReferenceTables($primary)
	{
		/** @var Storage\Table[] $tables */
		$tables = [
			Catalog\Run\Storage\OfferTable::class,
			Catalog\Run\Storage\AssortmentTable::class,
			Catalog\Run\Storage\PlacementTable::class,
			Catalog\Run\Storage\QueueTable::class,
			Catalog\Run\Storage\HashTable::class,
			Logger\Trading\Table::class => [
				'=SETUP_TYPE' => Catalog\Glossary::SERVICE_SELF,
				'=SETUP_ID' => $primary,
			],
		];

		foreach ($tables as $key => $table)
		{
			if (is_array($table))
			{
				$filter = $table;
				$table = $key;
			}
			else
			{
				$filter = [ '=CATALOG_ID' => $primary ];
			}

			$table::deleteBatch([ 'filter' => $filter ]);
		}
	}

	protected static function deleteReferenceChanges($primary)
	{
		Watcher\Track\StampTable::deleteBatch([
			'filter' => [
				'=SERVICE' => Catalog\Glossary::SERVICE_SELF,
				'=SETUP_ID' => $primary,
			]
		]);
	}

	protected static function onBeforeRemove($primary)
	{
		Model::loadById($primary)->onBeforeRemove();
	}

	protected static function onAfterSave($primary)
	{
		Model::loadById($primary)->onAfterSave();
	}
}
