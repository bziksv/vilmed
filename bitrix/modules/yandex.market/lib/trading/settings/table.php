<?php
namespace Yandex\Market\Trading\Settings;

use Bitrix\Main;
use Yandex\Market\Migration;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Trading;
use Yandex\Market\Utils;

class Table extends Storage\Table
{
	const ENTITY_TYPE_BUSINESS = 'business';
	const ENTITY_TYPE_SETUP = 'setup';

	const SERIALIZED_PREFIX =  '__SERIALIZED__:';

	public static function getTableName()
	{
		return 'yamarket_trading_settings';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\EnumField('ENTITY_TYPE', [
				'required' => true,
				'primary' => true,
				'values' => [
					self::ENTITY_TYPE_BUSINESS,
					self::ENTITY_TYPE_SETUP,
				],
			]),
			new Main\Entity\IntegerField('ENTITY_ID', [
				'required' => true,
				'primary' => true,
			]),
			new Main\Entity\StringField('NAME', [
				'required' => true,
				'primary' => true,
			]),
			new Main\Entity\TextField('VALUE', [
				'required' => true,
				'save_data_modification' => function() {
					return [
						function($value) {
							if ($value === null || is_scalar($value)) { return $value; }

							return self::SERIALIZED_PREFIX . Utils\PhpSerializer::encode($value);
						},
					];
				},
				'fetch_data_modification' => function() {
					return [
						function ($value) {
							if (!is_string($value) || mb_strpos($value, self::SERIALIZED_PREFIX) !== 0) { return $value; }

							$serialized = mb_substr($value, mb_strlen(self::SERIALIZED_PREFIX));
							$unserialized = Utils\PhpSerializer::decode($serialized);

							if ($unserialized === false) { return null; }

							return $unserialized;
						},
					];
				},
			]),

			new Main\Entity\ReferenceField('SETUP', Trading\Setup\Table::class, [
				'=this.SETUP_ID' => 'ref.ID',
			]),
		];
	}

	public static function isValidData($data)
	{
		return (is_array($data) && array_key_exists('VALUE', $data) && !Utils\Value::isEmpty($data['VALUE']));
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$existFields = $connection->getTableFields(self::getTableName());

		parent::migrate($connection);
		self::migrateSetupId($connection, $existFields);
	}

	private static function migrateSetupId(Main\DB\Connection $connection, array $existFields)
	{
		if (!isset($existFields['SETUP_ID'])) { return; }

		Migration\StorageFacade::dropPrimary($connection, self::getEntity(), [ 'SETUP_ID', 'NAME' ]);

		if (!isset($existFields['ENTITY_TYPE'], $existFields['ENTITY_ID']))
		{
			$sqlHelper = $connection->getSqlHelper();

			$connection->queryExecute(sprintf(
				'UPDATE %s SET %s="%s", %s=%s',
				$sqlHelper->quote(self::getTableName()),
				$sqlHelper->quote('ENTITY_TYPE'),
				$sqlHelper->forSql(self::ENTITY_TYPE_SETUP),
				$sqlHelper->quote('ENTITY_ID'),
				$sqlHelper->quote('SETUP_ID')
			));
		}

		$connection->dropColumn(self::getTableName(), 'SETUP_ID');
		Migration\StorageFacade::createPrimary($connection, self::getEntity());
	}
}
