<?php
namespace Yandex\Market\Trading\State\Internals;

use Yandex\Market;
use Bitrix\Main;

class OrderReturnTable extends Market\Reference\Storage\Table
{
	const STATUS_PROCESS = 0;
	const STATUS_SUCCESS = 1;
	const STATUS_FAIL = 2;

	public static function getTableName()
	{
		return 'yamarket_trading_order_return';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('CAMPAIGN_ID', [
				'required' => true,
				'primary' => true,
			]),
			new Main\Entity\StringField('ORDER_ID', [
				'required' => true,
				'primary' => true,
				'validation' => function() {
					return [
						new Main\Entity\Validator\Length(null, 60),
					];
				},
			]),
			new Main\Entity\EnumField('STATUS', [
				'values' => [
					static::STATUS_PROCESS,
					static::STATUS_SUCCESS,
					static::STATUS_FAIL,
				],
			]),
			new Market\Reference\Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
				'required' => true,
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
			'OrderReturnCompatibleTable',
			[
				new Main\Entity\IntegerField('SETUP_ID', [ 'primary' => true ]),
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

			if ($campaignId <= 0)
			{
				(new Market\Reference\Storage\Batch\DeleteBatch($compatibleTable))->run([
					'filter' => [ '=SETUP_ID' => $tradingId ],
				]);
				continue;
			}

			(new Market\Reference\Storage\Batch\UpdateBatch($compatibleTable))->run([
				'filter' => [ '=SETUP_ID' => $tradingId ],
			], [
				'SETUP_ID' => $campaignId,
			]);
		}
	}
}