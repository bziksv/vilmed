<?php
namespace Yandex\Market\Trading\Setup;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Trading\Service;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Settings;
use Yandex\Market\Migration;
use Yandex\Market\Data;
use Yandex\Market\Ui;

class Table extends Storage\Table
{
	use Concerns\HasMessage;

	private static $needCheckBusiness = [];

	public static function getTableName()
	{
		return 'yamarket_trading_setup';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true,
			]),
			new Main\Entity\BooleanField('ACTIVE', [
				'values' => [static::BOOLEAN_N, static::BOOLEAN_Y],
				'default_value' => static::BOOLEAN_Y,
			]),
			new Main\Entity\StringField('TRADING_SERVICE', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 20) ];
				},
			]),
			new Main\Entity\StringField('TRADING_BEHAVIOR', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 20) ];
				},
				'default_value' => Service\Manager::BEHAVIOR_DEFAULT,
			]),
			new Main\Entity\StringField('CODE', [
				'nullable' => true,
				'validation' => function() {
					return [
						new Main\Entity\Validator\Length(null, 10),
						static function ($value, $primary, $row)
						{
							$value = trim($value);

							if ($value === '') { return true; }

							if (!preg_match('/^[a-z0-9_-]+$/i', $value))
							{
								return self::getMessage('CODE_INVALID_CHARS');
							}

							if (self::testCodeChanged($value, $primary) && !self::testCodeUnique($value, $primary, $row))
							{
								return self::getMessage('CODE_NOT_UNIQUE');
							}

							return true;
						},
					];
				},
			]),
			new Main\Entity\StringField('SITE_ID', [
				'required' => true,
				'validation' => function() {
					return [ new Main\Entity\Validator\Length(null, 10) ];
				},
			]),
			new Main\Entity\IntegerField('BUSINESS_ID', [
				'required' => true,
			]),
			new Main\Entity\IntegerField('CAMPAIGN_ID', [
				'required' => true,
				'default_value' => 0,
			]),
			new Main\Entity\ReferenceField('SETTINGS', Settings\Table::class, [
				'=ref.ENTITY_TYPE' => [ '?', Settings\Table::ENTITY_TYPE_SETUP ],
				'=ref.ENTITY_ID' => 'this.ID',
			]),
			new Main\Entity\ReferenceField('BUSINESS', Business\Table::class, [
				'=ref.ID' => 'this.BUSINESS_ID',
			]),
			new Main\Entity\ExpressionField('BUSINESS_NAME', '%s', 'BUSINESS.NAME'),
			new Main\Entity\ReferenceField('CAMPAIGN', Campaign\Table::class, [
				'=ref.ID' => 'this.CAMPAIGN_ID',
			]),
			new Main\Entity\ExpressionField('CAMPAIGN_NAME', '%s', 'CAMPAIGN.NAME'),
			new Main\Entity\ExpressionField('CAMPAIGN_PLACEMENT', '%s', 'CAMPAIGN.PLACEMENT'),

			// compatible

			new Main\Entity\ExpressionField('EXTERNAL_ID', '%s', 'BUSINESS.PLATFORM_ID'),
			new Main\Entity\ExpressionField('NAME', '%s', 'CAMPAIGN.NAME'),
		];
	}

	public static function getMapDescription()
	{
		$result = parent::getMapDescription();

		$result['SITE_ID']['USER_TYPE'] = Ui\UserField\Manager::getUserType('enumeration');
		$result['SITE_ID']['VALUES'] = Data\Site::getSortedEnum();

		$result['ACTIVE']['SETTINGS'] = [ 'USE_ICON' => 'Y' ];

		return $result;
	}

	public static function onAfterUpdate(Main\Entity\Event $event)
	{
		$cache = Main\Application::getInstance()->getManagedCache();
		$tableName = static::getTableName();

		$cache->cleanDir($tableName);
	}

	public static function onBeforeDelete(Main\Entity\Event $event)
	{
		$idParameter = $event->getParameter('id');

		$tradingId = (int)(is_array($idParameter) ? $idParameter['ID'] : $idParameter);
		$tradingRow = static::getRow([
			'filter' => [ '=ID' => $tradingId ],
			'select' => [ 'BUSINESS_ID', 'TRADING_BEHAVIOR' ],
		]);

		$eventResult = new Main\Entity\EventResult();

		if ($tradingRow === null) { return $eventResult; }

		$businessId = (int)$tradingRow['BUSINESS_ID'];

		if ($businessId <= 0) { return $eventResult; }

		$business = new Business\Model([ 'ID' => $businessId ]);

		if ($business->getTradingCollection()->exceptItemId($tradingId)->count() > 0)
		{
			if ($tradingRow['TRADING_BEHAVIOR'] === Service\Manager::BEHAVIOR_BUSINESS)
			{
				self::$needCheckBusiness[$tradingId] = $business;
			}

			return $eventResult;
		}

		if ($business->getCatalog() !== null)
		{
			$eventResult->addError(new Main\Entity\EntityError(self::getMessage('NEED_DELETE_CATALOG', [
				'#BUSINESS_ID#' => $businessId,
				'#URL#' => Ui\Admin\Path::getModuleUrl('catalog_list', Ui\Trading\Menu::compileQuery($businessId)),
			])));

			return $eventResult;
		}

		if ($business->getSalesBoostCollection()->count() > 0)
		{
			$eventResult->addError(new Main\Entity\EntityError(self::getMessage('NEED_DELETE_SALES_BOOST', [
				'#BUSINESS_ID#' => $businessId,
				'#URL#' => Ui\Admin\Path::getModuleUrl('sales_list', Ui\Trading\Menu::compileQuery($businessId)),
			])));

			return $eventResult;
		}

		self::$needCheckBusiness[$tradingId] = $business;

		return $eventResult;
	}

	public static function onAfterDelete(Main\Entity\Event $event)
	{
		$idParameter = $event->getParameter('id');

		$tradingId = (int)(is_array($idParameter) ? $idParameter['ID'] : $idParameter);
		$menuCompiler = new Ui\Trading\MenuCompiler();

		self::checkBusiness($menuCompiler, $tradingId);
		self::checkMenuOther($menuCompiler);

		$menuCompiler->save();
	}

	private static function checkBusiness(Ui\Trading\MenuCompiler $menuCompiler, $tradingId)
	{
		/** @var Business\Model $business */
		foreach (self::$needCheckBusiness as $business)
		{
			$tradingRepository = $business->getTradingRepository();

			if ($tradingRepository->someoneUsingBehavior(Service\Manager::BEHAVIOR_BUSINESS, $tradingId)) { continue; }

			$menuCompiler->ejectBusinessBehavior($business->getId());

			if ($business->getTradingCollection()->exceptItemId($tradingId)->count() > 0) { continue; }

			$business->uninstall($menuCompiler);
			$business->delete();
		}

		self::$needCheckBusiness = [];
	}

	private static function checkMenuOther(Ui\Trading\MenuCompiler $menuCompiler)
	{
		$menuCompiler->extractOther();
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		parent::migrate($connection);
		static::migrateIncreaseServiceLength($connection);
		static::migrateFillDefaultBehavior($connection);
		static::migrateCode($connection);
		static::migrateDefaultBusinessAndCampaignId();
	}

	private static function migrateIncreaseServiceLength(Main\DB\Connection $connection)
	{
		$entity = static::getEntity();

		Migration\StorageFacade::updateFieldsLength($connection, $entity, [ 'TRADING_SERVICE' ]);
	}

	private static function migrateFillDefaultBehavior(Main\DB\Connection $connection)
	{
		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %1$s SET %2$s=\'%3$s\' WHERE %2$s is null or %2$s=\'\'',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('TRADING_BEHAVIOR'),
			$sqlHelper->forSql(Service\Manager::BEHAVIOR_DEFAULT)
		));
	}

	private static function migrateCode(Main\DB\Connection $connection)
	{
		$sqlHelper = $connection->getSqlHelper();
		$tableName = static::getTableName();

		$connection->queryExecute(sprintf(
			'UPDATE %1$s SET %2$s=%3$s WHERE %2$s is null or %2$s=\'\'',
			$sqlHelper->quote($tableName),
			$sqlHelper->quote('CODE'),
			$sqlHelper->quote('SITE_ID')
		));
	}

	private static function migrateDefaultBusinessAndCampaignId()
	{
		self::updateBatch([
			'filter' => [
				'BUSINESS_ID' => false,
				'CAMPAIGN_ID' => false,
			],
		], [
			'BUSINESS_ID' => 0,
			'CAMPAIGN_ID' => 0,
		]);
	}

	public static function getReference($primary = null)
	{
		return [
			'SETTINGS' => [
				'TABLE' => Settings\Table::class,
				'LINK_FIELD' => [ 'ENTITY_TYPE', 'ENTITY_ID' ],
				'LINK' => [
					'ENTITY_TYPE' => Settings\Table::ENTITY_TYPE_SETUP,
					'ENTITY_ID' => $primary,
				],
			],
		];
	}

	private static function testCodeChanged($code, $primary)
	{
		if ($primary === null) { return true; }

		$result = true;
		$primaryId = is_scalar($primary) ? $primary : $primary['ID'];

		$query = static::getList([
			'filter' => [ '=ID' => $primaryId ],
			'select' => [ 'CODE' ],
		]);

		if ($exists = $query->fetch())
		{
			$result = ((string)$exists['CODE'] !== (string)$code);
		}

		return $result;
	}

	private static function testCodeUnique($code, $primary, $row)
	{
		$row = static::fulfilExistsData($primary, $row, [
			'TRADING_SERVICE',
			'TRADING_BEHAVIOR',
		]);

		$filter = [
			'=TRADING_SERVICE' => $row['TRADING_SERVICE'],
			'=TRADING_BEHAVIOR' => $row['TRADING_BEHAVIOR'],
			'=CODE' => $code,
		];

		if ($primary !== null)
		{
			$primaryId = is_scalar($primary) ? $primary : $primary['ID'];

			$filter['!=ID'] = $primaryId;
		}

		$row = static::getList([ 'filter' => $filter, 'limit' => 1 ])->fetch();

		return ($row === false);
	}

	private static function fulfilExistsData($primary, $row, $select)
	{
		$needSelect = array_diff_key(array_flip($select), $row);

		if (empty($needSelect)) { return $row; }

		$primaryId = is_scalar($primary) ? $primary : $primary['ID'];

		$query = static::getList([
			'filter' => [ '=ID' => $primaryId ],
			'select' => array_keys($needSelect),
		]);

		if ($exists = $query->fetch())
		{
			$row += $exists;
		}

		return $row;
	}
}
