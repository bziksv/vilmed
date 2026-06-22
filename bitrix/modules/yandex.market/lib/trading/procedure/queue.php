<?php

namespace Yandex\Market\Trading\Procedure;

use Yandex\Market;
use Bitrix\Main;

class QueueTable extends Market\Reference\Storage\Table
{
	public static function getTableName()
	{
		return 'yamarket_trading_queue';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'primary' => true,
				'autocomplete' => true,
			]),
			new Main\Entity\IntegerField('CAMPAIGN_ID', [
				'required' => true,
			]),
			new Main\Entity\StringField('PATH', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 50) ];
				},
			]),
			new Main\Entity\TextField('DATA', [
				'required' => true,
				'serialized' => true,
			]),
			new Main\Entity\EnumField('ENTITY_TYPE', [
				'required' => true,
				'values' => [
					Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				],
			]),
			new Main\Entity\StringField('ENTITY_ID', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 50) ];
				},
			]),
			new Main\Entity\DatetimeField('EXEC_DATE', [
				'required' => true,
			]),
			new Main\Entity\IntegerField('EXEC_COUNT', [
				'default_value' => 0,
			]),
			new Main\Entity\IntegerField('INTERVAL', [
				'required' => true,
				'default_value' => 3600,
			]),
		];
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$tableFields = $connection->getTableFields(self::getTableName());

		self::migrateSetupId($connection, $tableFields);
	}

	private static function migrateSetupId(Main\DB\Connection $connection, $tableFields)
	{
		if (!isset($tableFields['SETUP_ID'])) { return; }

		self::migrateSetupIdToCampaignIdValue($connection);
		Market\Migration\StorageFacade::renameColumn($connection, self::getEntity(), 'SETUP_ID', 'CAMPAIGN_ID');
	}

	private static function migrateSetupIdToCampaignIdValue(Main\DB\Connection $connection)
	{
		/** @var class-string<Market\Reference\Storage\Table> $compatibleTable */
		$compatibleTable = Main\Entity\Base::compileEntity(
			'QueueCompatibleTable',
			[
				new Main\Entity\IntegerField('ID', [ 'primary' => true ]),
				new Main\Entity\IntegerField('SETUP_ID'),
			],
			[
				'table_name' => self::getTableName(),
				'namespace' => __NAMESPACE__,
			]
		)->getDataClass();

		$tradingIds = array_column($compatibleTable::getList([
			'select' => [ 'SETUP_ID' ],
			'group' => [ 'SETUP_ID' ],
		])->fetchAll(), 'SETUP_ID', 'SETUP_ID');

		if (empty($tradingIds)) { return; }

		Market\Trading\Setup\Table::migrate($connection);

		$tradings = Market\Trading\Setup\Model::loadList([
			'filter' => [ '=ID' => $tradingIds ],
		]);

		foreach ($tradings as $trading)
		{
			$tradingId = $trading->getId();

			if (!$trading->isActive())
			{
				(new Market\Reference\Storage\Batch\DeleteBatch($compatibleTable))->run([
					'filter' => [ '=SETUP_ID' => $tradingId ],
				]);
				continue;
			}

			$campaignId = (int)($trading->getCampaignId() ?: $trading->getSettings()->getValue('CAMPAIGN_ID'));

			if ($campaignId > 0)
			{
				(new Market\Reference\Storage\Batch\UpdateBatch($compatibleTable))->run([
					'filter' => [ '=SETUP_ID' => $tradingId ],
				], [
					'SETUP_ID' => $campaignId,
				]);
			}
			else
			{
				(new Market\Reference\Storage\Batch\DeleteBatch($compatibleTable))->run([
					'filter' => [ '=SETUP_ID' => $tradingId ],
				]);
			}
		}
	}
}