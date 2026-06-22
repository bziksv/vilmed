<?php
namespace Yandex\Market\Trading\Campaign;

use Bitrix\Main;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Trading;

class Table extends Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_trading_campaign';
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
			new Main\Entity\EnumField('PLACEMENT', [
				'required' => true,
				'values' => Placement::all(),
			]),
			new Main\Entity\IntegerField('BUSINESS_ID', [
				'required' => true,
			]),
			new Main\Entity\IntegerField('TRADING_ID', [
				'nullable' => true,
				'default_value' => 0,
			]),
			new Main\Entity\TextField('EXTERNAL_SETTINGS', Storage\Field\JsonSerializer::getParameters() + [
				'nullable' => true,
			]),
			new Main\Entity\ReferenceField('BUSINESS', Trading\Business\Table::class, [
				'=ref.ID' => 'this.BUSINESS_ID',
			]),
			new Main\Entity\ReferenceField('TRADING', Trading\Setup\Table::class, [
				'=ref.ID' => 'this.TRADING_ID',
			]),
		];
	}
}
