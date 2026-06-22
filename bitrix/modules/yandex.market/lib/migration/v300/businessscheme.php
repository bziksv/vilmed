<?php
namespace Yandex\Market\Migration\V300;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Component;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Storage\Batch\UpdateBatch;
use Yandex\Market\State;
use Yandex\Market\Trading;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Utils\ArrayHelper;

class BusinessScheme
{
	private $connection;
	private $deprecatedColumns = [];

	public function __construct()
	{
		$this->connection = Main\Application::getConnection();
	}

	public function wasApplied()
	{
		return (State::get('business_scheme') === 'v3');
	}

	private function commitApplied()
	{
		State::set('business_scheme', 'v3');
	}

	public function apply()
	{
		$tradingCollection = $this->tradingCollection();
		$tradingCompatibleColumns = $this->tradingCompatibleColumns($tradingCollection);

		$tradingCollection = $this->fillTradingBusinessAndCampaign($tradingCollection);
		$businessGroups = $this->groupByBusiness($tradingCollection);

		$businesses = $this->compileBusinesses($businessGroups);
		$this->compileCampaigns($businesses, $businessGroups, $tradingCompatibleColumns);
		$this->migratePlatform($businesses, $businessGroups, $tradingCompatibleColumns);

		$this->dropDeprecatedColumns();
		$this->commitApplied();
	}

	private function tradingCollection()
	{
		return Trading\Setup\Collection::loadByFilter([
			'filter' => [ '=TRADING_SERVICE' => Trading\Service\Manager::SERVICE_MARKETPLACE ],
		]);
	}

	private function tradingCompatibleColumns(Trading\Setup\Collection $tradingCollection)
	{
		if ($tradingCollection->count() === 0) { return []; }

		$tableName = Trading\Setup\Table::getTableName();
		$columns = $this->connection->getTableFields($tableName);

		if (!isset($columns['EXTERNAL_ID'], $columns['NAME'])) { return []; }

		$sqlHelper = $this->connection->getSqlHelper();

		$rows = $this->connection->query(sprintf(
			'SELECT %s FROM %s',
			implode(', ', [
				$sqlHelper->quote('ID'),
				$sqlHelper->quote('EXTERNAL_ID'),
				$sqlHelper->quote('NAME'),
			]),
			$sqlHelper->quote($tableName)
		))->fetchAll();

		$this->deprecatedColumns[$tableName] = [ 'EXTERNAL_ID', 'NAME' ];

		return ArrayHelper::columnToKey($rows, 'ID');
	}

	private function fillTradingBusinessAndCampaign(Trading\Setup\Collection $tradingCollection)
	{
		/** @var Trading\Setup\Model $trading */
		foreach ($tradingCollection as $trading)
		{
			$businessId = $trading->getBusinessId();

			if ($businessId > 0) { continue; }

			$settings = $trading->getSettings()->getValues();

			if (!isset($settings['BUSINESS_ID'])) { continue; }

			$trading->setField('BUSINESS_ID', (int)$settings['BUSINESS_ID']);
			$trading->setField('CAMPAIGN_ID', (int)$settings['CAMPAIGN_ID']);
			$trading->setField('SETTINGS', $this->settingsToRows(array_diff_key($settings, [
				'BUSINESS_ID' => true,
				'CAMPAIGN_ID' => true,
			])));

			$trading->save();
		}

		return $tradingCollection;
	}

	private function groupByBusiness(Trading\Setup\Collection $tradingCollection)
	{
		$groups = [];

		/** @var Trading\Setup\Model $trading */
		foreach ($tradingCollection as $trading)
		{
			$businessId = $trading->getBusinessId();

			if ($businessId <= 0) { continue; }

			if (!isset($groups[$businessId]))
			{
				$groups[$businessId] = new Trading\Setup\Collection();
			}

			$groups[$businessId]->addItem($trading);
		}

		return $groups;
	}

	private function compileBusinesses(array $businessGroups)
	{
		$businesses = $this->storedBusinesses();
		$businessIds = array_unique(array_merge(
			array_keys($businesses),
			array_keys($businessGroups)
		));

		foreach ($businessIds as $businessId)
		{
			if (isset($businesses[$businessId]))
			{
				$business = $businesses[$businessId];

				if (!isset($businessGroups[$businessId]))
				{
					$business->setField('ACTIVE', UserField\BooleanType::VALUE_N);
					$business->save();
					continue;
				}
			}
			else
			{
				if (!isset($businessGroups[$businessId])) { continue; }

				$business = new Trading\Business\Model();
				$business->setField('ID', $businessId);
				$business->setFallbackName();

				$businesses[$businessId] = $business;
			}

			/** @var Trading\Setup\Collection $tradingCollection */
			$tradingCollection = $businessGroups[$businessId];
			$primarySetup = $this->primaryBusinessSetup($tradingCollection);
			$migrator = new Component\Business\OptionsMigrator();

			if ($business->getSiteId() === '')
			{
				$business->setField('SITE_ID', $primarySetup->getSiteId());
			}

			$businessSettings = $business->getSettings()->getValues() + $migrator->compile($tradingCollection);

			$business->setField('SETTINGS', $this->settingsToRows($businessSettings));

			$business->save();
			$migrator->commit();
		}

		return $businesses;
	}

	/** @return array<int, Trading\Business\Model> */
	private function storedBusinesses()
	{
		$result = [];

		foreach (Trading\Business\Model::loadList() as $business)
		{
			$result[$business->getId()] = $business;
		}

		return $result;
	}

	private function primaryBusinessSetup(Trading\Setup\Collection $tradingCollection)
	{
		$significantCollection = $tradingCollection->filterActive();

		if ($significantCollection->count() === 0) { $significantCollection = $tradingCollection; }

		$primarySetup = (
			$significantCollection->getByBehavior(Trading\Service\Manager::BEHAVIOR_BUSINESS)
			?: $significantCollection->offsetGet(0)
		);

		Assert::notNull($primarySetup, 'primaryBusinessSetup');

		return $primarySetup;
	}

	private function settingsToRows(array $values)
	{
		return array_map(
			static function($key, $value) { return [ 'NAME' => $key, 'VALUE' => $value ]; },
			array_keys($values),
			array_values($values)
		);
	}

	private function compileCampaigns(array $businesses, array $businessGroups, array $tradingCompatibleColumns)
	{
		/** @var Trading\Business\Model $business */
		foreach ($businesses as $businessId => $business)
		{
			if (!isset($businessGroups[$businessId])) { continue; }

			/** @var Trading\Setup\Collection $tradingCollection */
			$tradingMap = $this->mapTradingByCampaign($businessGroups[$businessId]);
			$campaignCollection = $business->getCampaignCollection();

			/** @var Trading\Setup\Model $trading */
			foreach ($tradingMap as $campaignId => $trading)
			{
				if ($campaignCollection->getItemById($campaignId) !== null) { continue; }

				$tradingId = $trading->getId();
				$tradingSettings = $trading->getSettings()->getValues();

				$campaign = new Trading\Campaign\Model();
				$campaign->setField('BUSINESS_ID', $businessId);
				$campaign->setField('ID', $campaignId);
				$campaign->setField('TRADING_ID', $trading->isActive() ? $tradingId : 0);
				$campaign->setField('PLACEMENT', Trading\Campaign\Placement::toPlacement($trading->getBehaviorCode()));

				if (isset($tradingSettings['STORE_DATA']['PRIMARY_CAMPAIGN']))
				{
					$campaign->setField('EXTERNAL_SETTINGS', $campaign->getExternalSettings()->extendValues([
						'WAREHOUSE_GROUP_PRIMARY' => $tradingSettings['STORE_DATA']['PRIMARY_CAMPAIGN'],
					])->getValues());
				}

				if (isset($tradingCompatibleColumns[$tradingId]['NAME']) && $tradingCompatibleColumns[$tradingId]['NAME'] !== $trading->getDefaultName())
				{
					$campaign->setField('NAME', $tradingCompatibleColumns[$tradingId]['NAME']);
				}
				else
				{
					$campaign->setFallbackName();
				}

				$campaign->save();

				$campaignCollection->addItem($campaign);
				$campaign->setParentCollection($campaignCollection);

				$this->migrateOrderSyncShift($tradingId, $campaignId);
			}
		}
	}

	private function mapTradingByCampaign(Trading\Setup\Collection $tradingCollection)
	{
		$result = [];

		/** @var Trading\Setup\Model $trading */
		foreach ($tradingCollection as $trading)
		{
			$campaignId = $trading->getCampaignId();

			if ($campaignId <= 0) { continue; }

			if (
				!isset($result[$campaignId])
				|| ($trading->isActive() && !$result[$campaignId]->isActive())
			)
			{
				$result[$campaignId] = $trading;
			}
		}

		return $result;
	}

	private function migrateOrderSyncShift($tradingId, $campaignId)
	{
		$date = (string)State::get("trading_status_sync_updated_{$tradingId}");

		if ($date === '') { return; }

		State::remove("trading_status_sync_updated_{$tradingId}");
		State::set("trading_status_sync_updated_{$campaignId}", $date);
	}

	private function migratePlatform(array $businesses, array $businessGroups, array $tradingCompatibleColumns)
	{
		$configured = array_flip(array_filter(array_map(
			static function(Trading\Business\Model $business) { return $business->getPlatformId(); },
			$businesses
		)));
		$unallocated = array_diff_key(
			$this->unallocatedPlatforms($businessGroups, $tradingCompatibleColumns),
			array_flip($configured)
		);

		/** @var Trading\Business\Model $business */
		foreach ($businesses as $businessId => $business)
		{
			if (!isset($businessGroups[$businessId]) || $business->getPlatformId() > 0) { continue; }

			$tradingCollection = $businessGroups[$businessId];
			$businessPlatformId = $this->chooseBusinessPlatform($business, $tradingCollection, $tradingCompatibleColumns, $unallocated);

			foreach ($this->usedTradingPlatforms($tradingCollection, $tradingCompatibleColumns) as $platformId => $tradingIds)
			{
				if (isset($unallocated[$platformId]) && count($unallocated[$platformId]) === 1)
				{
					$this->moveOrderPlatform($platformId, $businessPlatformId);
				}
				else
				{
					$this->moveOrderPlatform($platformId, $businessPlatformId, $tradingIds);
				}
			}

			if (isset($unallocated[$businessPlatformId]))
			{
				unset($unallocated[$businessPlatformId]);
			}
		}
	}

	private function unallocatedPlatforms(array $businessGroups, array $tradingCompatibleColumns)
	{
		$platformBusinesses = [];

		foreach ($businessGroups as $businessId => $businessGroup)
		{
			foreach ($this->usedTradingPlatforms($businessGroup, $tradingCompatibleColumns) as $platformId => $tradingIds)
			{
				if (!isset($platformBusinesses[$platformId]))
				{
					$platformBusinesses[$platformId] = [];
				}

				$platformBusinesses[$platformId][] = $businessId;
			}
		}

		return $platformBusinesses;
	}

	private function usedTradingPlatforms(Trading\Setup\Collection $tradingCollection, array $tradingCompatibleColumns)
	{
		$usageMap = [];

		/** @var Trading\Setup\Model $trading */
		foreach ($tradingCollection as $trading)
		{
			$id = $trading->getId();

			if (!isset($tradingCompatibleColumns[$id]['EXTERNAL_ID'])) { continue; }

			$platformId = (int)$tradingCompatibleColumns[$id]['EXTERNAL_ID'];

			if ($platformId <= 0) { continue; }

			if (!isset($usageMap[$platformId]))
			{
				$usageMap[$platformId] = [];
			}

			$usageMap[$platformId][] = $id;
		}

		return $usageMap;
	}

	private function chooseBusinessPlatform(Trading\Business\Model $business, Trading\Setup\Collection $tradingCollection, array $tradingCompatibleColumns, array $unallocated)
	{
		$firstTrading = null;

		/** @var Trading\Setup\Model $trading */
		foreach ($tradingCollection as $trading)
		{
			if ($firstTrading === null) { $firstTrading = $trading; }

			$tradingId = $trading->getId();

			if (empty($tradingCompatibleColumns[$tradingId]['EXTERNAL_ID'])) { continue; }

			$platformId = (int)$tradingCompatibleColumns[$tradingId]['EXTERNAL_ID'];

			if (isset($unallocated[$platformId]))
			{
				return $business->getTradingRepository()->migratePlatform($trading->getEnvironment(), $platformId);
			}
		}

		Assert::notNull($firstTrading, 'firstTrading');

		return $business->getTradingRepository()->installPlatform($firstTrading->getEnvironment());
	}

	private function moveOrderPlatform($fromPlatformId, $toPlatformId, array $setupIds = null)
	{
		if ($fromPlatformId === $toPlatformId) { return; }
		if (!Main\Loader::includeModule('sale')) { return; }

		$filter = [
			'=TRADING_PLATFORM_ID' => $fromPlatformId,
		];

		if ($setupIds !== null)
		{
			$filter['=PARAMS'] = array_map(
				static function($setupId) { return serialize([ 'SETUP_ID' => (string)$setupId ]); },
				$setupIds
			);
		}

		(new UpdateBatch(Sale\TradingPlatform\OrderTable::class))->run([
			'filter' => $filter,
		], [
			'TRADING_PLATFORM_ID' => $toPlatformId,
		]);
	}

	private function dropDeprecatedColumns()
	{
		foreach ($this->deprecatedColumns as $tableName => $columns)
		{
			foreach ($columns as $column)
			{
				$this->connection->dropColumn($tableName, $column);
			}
		}
	}
}