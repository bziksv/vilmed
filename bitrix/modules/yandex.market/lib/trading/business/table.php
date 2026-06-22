<?php
namespace Yandex\Market\Trading\Business;

use Bitrix\Main;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Trading;
use Yandex\Market\Catalog;
use Yandex\Market\SalesBoost;

class Table extends Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_trading_business';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'primary' => true,
			]),
			new Main\Entity\StringField('NAME', [
				'required' => true,
			]),
			new Main\Entity\StringField('SITE_ID', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 10) ];
				},
			]),
			new Main\Entity\IntegerField('PLATFORM_ID', [
				'default_value' => 0,
				'nullable' => true,
			]),
			new Main\Entity\TextField('EXTERNAL_SETTINGS', Storage\Field\JsonSerializer::getParameters() + [
				'nullable' => true,
			]),

			new Main\Entity\ReferenceField('SETTINGS', Trading\Settings\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Trading\Settings\Table::ENTITY_TYPE_BUSINESS ],
				'=ref.ENTITY_ID' => 'this.ID',
			]),
			new Main\Entity\ReferenceField('TRADING', Trading\Setup\Table::class, [
				'=ref.BUSINESS_ID' => 'this.ID',
			]),
			new Main\Entity\ReferenceField('CATALOG', Catalog\Setup\Table::class, [
				'=ref.BUSINESS_ID' => 'this.ID',
			]),
			new Main\Entity\ReferenceField('CAMPAIGN', Trading\Campaign\Table::class, [
				'=ref.BUSINESS_ID' => 'this.ID',
			]),
			new Main\Entity\ReferenceField('SALES_BOOST', SalesBoost\Setup\Table::class, [
				'=ref.BUSINESS_ID' => 'this.ID',
			]),
		];
	}

	public static function getReference($primary = null)
	{
		return [
			'SETTINGS' => [
				'TABLE' => Trading\Settings\Table::class,
				'LINK_FIELD' => [ 'ENTITY_TYPE', 'ENTITY_ID' ],
				'LINK' => [
					'ENTITY_TYPE' => Trading\Settings\Table::ENTITY_TYPE_BUSINESS,
					'ENTITY_ID' => $primary,
				],
			],
			'CAMPAIGN' => [
				'TABLE' => Trading\Campaign\Table::class,
				'LINK_FIELD' => 'BUSINESS_ID',
				'LINK' => [
					'BUSINESS_ID' => $primary,
				],
				'ORDER' => [ 'ID' => 'ASC' ],
			],
			'CATALOG' => [
				'UPDATABLE' => false,
				'TABLE' => Catalog\Setup\Table::class,
				'LINK_FIELD' => 'BUSINESS_ID',
				'LINK' => [
					'BUSINESS_ID' => $primary,
				],
			],
			'TRADING' => [
				'UPDATABLE' => false,
				'TABLE' => Trading\Setup\Table::class,
				'LINK_FIELD' => 'BUSINESS_ID',
				'LINK' => [
					'BUSINESS_ID' => $primary,
				],
				'ORDER' => [
					'ACTIVE' => 'DESC',
					'ID' => 'ASC',
				],
			],
			'SALES_BOOST' => [
				'UPDATABLE' => false,
				'TABLE' => SalesBoost\Setup\Table::class,
				'LINK_FIELD' => 'BUSINESS_ID',
				'LINK' => [
					'BUSINESS_ID' => $primary,
				],
			],
		];
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$existFields = $connection->getTableFields(static::getTableName());

		parent::migrate($connection);
		self::dropActive($connection, $existFields);
	}

	private static function dropActive(Main\DB\Connection $connection, array $tableFields)
	{
		if (!isset($tableFields['ACTIVE'])) { return; }

		$connection->dropColumn(static::getTableName(), 'ACTIVE');
	}
}
