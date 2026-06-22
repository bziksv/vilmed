<?php
namespace Yandex\Market\Logger\Trading;

use Yandex\Market\Catalog;
use Yandex\Market\Glossary;
use Yandex\Market\Logger as GlobalLogger;
use Yandex\Market\Migration;
use Yandex\Market\Trading;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Utils\ArrayHelper;
use Bitrix\Main;

class Table extends Storage\Table
{
	use Concerns\HasMessage;

	public static function getTableName()
	{
		return 'yamarket_trading_log';
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		$tableName = static::getTableName();

		$connection->createIndex($tableName, 'IX_' . $tableName . '_1', [ 'ENTITY_TYPE', 'ENTITY_ID' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_2', [ 'AUDIT' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_3', [ 'SETUP_TYPE', 'SETUP_ID' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_4', [ 'BUSINESS_ID', 'CAMPAIGN_ID' ]);
		$connection->createIndex($tableName, 'IX_' . $tableName . '_5', [ 'TIMESTAMP_X' ]);
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true,
			]),
			new Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
				'required' => true,
			]),
			new Main\Entity\EnumField('LEVEL', [
				'required' => true,
				'values' => GlobalLogger\Level::getVariants(),
			]),
			new Main\Entity\TextField('MESSAGE', [
				'required' => true,
			]),
			new Main\Entity\EnumField('AUDIT', [
				'nullable' => true,
				'values' => array_merge(...array_values(Audit::getVariants())),
			]),
			new Main\Entity\StringField('URL', [
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 255) ];
				},
			]),
			new Main\Entity\StringField('ENTITY_TYPE', [
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 10) ];
				},
			]),
			new Main\Entity\StringField('ENTITY_ID', [
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 255) ];
				},
			]),
			new Main\Entity\EnumField('SETUP_TYPE', [
				'nullable' => true,
				'values' => [
					Glossary::SERVICE_TRADING,
					Glossary::SERVICE_CATALOG,
				],
			]),
			new Main\Entity\IntegerField('SETUP_ID', [
				'nullable' => true,
			]),
			new Main\Entity\IntegerField('BUSINESS_ID', [
				'default_value' => 0,
			]),
			new Main\Entity\IntegerField('CAMPAIGN_ID', [
				'default_value' => 0,
			]),
			new Main\Entity\TextField('CONTEXT', [
				'nullable' => true,
				'serialized' => true,
			]),
			new Main\Entity\TextField('TRACE', [
				'nullable' => true,
			]),

			new Main\Entity\ReferenceField('ASSORTMENT', Catalog\Run\Storage\AssortmentTable::class, [
				'=this.SETUP_TYPE' => [ '?', Glossary::SERVICE_CATALOG ],
				'=this.SETUP_ID' => 'ref.CATALOG_ID',
				'=this.ENTITY_TYPE' => [ '?', Catalog\Glossary::ENTITY_SKU ],
				'=this.ENTITY_ID' => 'ref.SKU',
			]),

			new Main\Entity\ReferenceField('BUSINESS', Trading\Business\Table::class, [
				'=this.BUSINESS_ID' => 'ref.ID',
			]),
			new Main\Entity\ReferenceField('CAMPAIGN', Trading\Campaign\Table::class, [
				'=this.CAMPAIGN_ID' => 'ref.ID',
			]),

			new Main\Entity\ReferenceField('TRADING', Trading\Setup\Table::class, [
				'=this.SETUP_TYPE' => [ '?', Glossary::SERVICE_TRADING  ],
				'=this.SETUP_ID' => 'ref.ID',
			]),
		];
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$existsFields = $connection->getTableFields(static::getTableName());

		Migration\StorageFacade::addNewFields($connection, static::getEntity());
		Migration\StorageFacade::updateFieldsLength($connection, static::getEntity(), [ 'ENTITY_TYPE', 'ENTITY_ID', 'AUDIT' ]);
		static::migrateContext($connection);
		static::migrateSetupType($connection, $existsFields);
		static::migrateBusinessId($connection, $existsFields);
	}

	private static function migrateContext(Main\DB\Connection $connection)
	{
		$entity = static::getEntity();
		$storedTypes = Migration\StorageFacade::getTableColumnTypes($connection, $entity);
		$columnName = 'CONTEXT';

		if (!isset($storedTypes[$columnName])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$field = $entity->getField($columnName);
		$fieldType = $sqlHelper->getColumnTypeByField($field);

		if (mb_strtolower($fieldType) === mb_strtolower($storedTypes[$columnName])) { return; }

		$connection->queryExecute(sprintf(
			'ALTER TABLE %s MODIFY COLUMN %s %s',
			$sqlHelper->quote($entity->getDBTableName()),
			$sqlHelper->quote($columnName),
			$fieldType
		));
	}

	private static function migrateSetupType(Main\DB\Connection $connection, array $tableColumns)
	{
		if (!isset($tableColumns['ENTITY_PARENT'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s=%s',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('SETUP_ID'),
			$sqlHelper->quote('ENTITY_PARENT')
		));

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s="%s"',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('SETUP_TYPE'),
			$sqlHelper->forSql(Glossary::SERVICE_TRADING)
		));

		$connection->dropColumn($tableName, 'ENTITY_PARENT');
		$connection->createIndex($tableName, 'IX_' . $tableName . '_3', [ 'SETUP_TYPE', 'SETUP_ID' ]);
	}

	private static function migrateBusinessId(Main\DB\Connection $connection, array $tableColumns)
	{
		if (isset($tableColumns['BUSINESS_ID'], $tableColumns['CAMPAIGN_ID'])) { return; }

		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %s SET %s=0, %s=0',
			$sqlHelper->quote(static::getTableName()),
			$sqlHelper->quote('BUSINESS_ID'),
			$sqlHelper->quote('CAMPAIGN_ID')
		));
		$connection->createIndex($tableName, 'IX_' . $tableName . '_4', [ 'BUSINESS_ID', 'CAMPAIGN_ID' ]);
	}

	public static function getMapDescription()
	{
		self::includeSelfMessages();

		$campaignGlue = self::getMessage('CAMPAIGN_GLUE', null, '');

		$result = parent::getMapDescription();
		$result['MESSAGE']['USER_TYPE'] = UserField\Manager::getUserType('logMessage');
		$result['LEVEL'] = static::extendLevelDescription($result['LEVEL']);
		$result['AUDIT'] = static::extendAuditDescription($result['AUDIT']);
		$result['TRACE']['USER_TYPE'] = UserField\Manager::getUserType('trace');
		$result['CONTEXT']['USER_TYPE'] = UserField\Manager::getUserType('logMessage');
		$result['BUSINESS_ID']['SELECTABLE'] = false;
		$result['CAMPAIGN']['SETTINGS']['NAME'] = [ "%s {$campaignGlue} %s [%s]", 'PLACEMENT', 'NAME', 'ID' ];

		return $result;
	}

	protected static function extendLevelDescription($field)
	{
		$field['USER_TYPE'] = UserField\Manager::getUserType('log');
		$allowedTypes = [
			GlobalLogger\Level::ERROR => true,
			GlobalLogger\Level::WARNING => true,
			GlobalLogger\Level::INFO => true,
			GlobalLogger\Level::NOTICE => true,
			GlobalLogger\Level::DEBUG => true,
		];

		foreach ($field['VALUES'] as $optionKey => &$option)
		{
			if (isset($allowedTypes[$option['ID']]))
			{
				$option['LOG_LEVEL'] = $option['ID'];
			}
			else
			{
				unset($field['VALUES'][$optionKey]);
			}
		}
		unset($option);

		return $field;
	}

	protected static function extendAuditDescription($field)
	{
		$groupsMap = ArrayHelper::flipMultidimensional(Audit::getVariants());

		foreach ($field['VALUES'] as &$option)
		{
			$option['VALUE'] = Audit::getTitle($option['ID']);
			$option['GROUP'] = Audit::getGroupTitle($groupsMap[$option['ID']]);
		}
		unset($option);

		return $field;
	}
}