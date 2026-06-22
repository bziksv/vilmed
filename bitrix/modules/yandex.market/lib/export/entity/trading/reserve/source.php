<?php
namespace Yandex\Market\Export\Entity\Trading\Reserve;

use Bitrix\Main;
use Bitrix\Catalog;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading;
use Yandex\Market\Trading\Service\Marketplace\Command as MarketplaceCommand;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Utils\ArrayHelper;

class Source extends Entity\Reference\Source
{
	use Concerns\HasMessage;

	/**
	 * @var array<int, array{
	 *     SOURCE_TYPE: string,
	 *     SOURCE_FIELD: string,
	 *     NEED_TOTAL: bool,
	 *     ORDER_LOADER: ?MarketplaceCommand\ProcessingOrders,
	 *     ENVIRONMENT: ?TradingEntity\Reference\Environment
	 * }>
	 */
	private $loaders;
	private $loadedOrders;

	public function getFields(array $context = [])
	{
		return [];
	}

	public function getOrder()
	{
		return 1100; // after templates
	}

	/** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
	public function initializeQueryContext($select, &$queryContext, &$sourceSelect)
	{
		$fieldMap = $this->parseSelectFieldMap($select, $queryContext);
		$tradingSetups = $this->tradingSetups(array_column($fieldMap, 'CAMPAIGN_ID'));
		$groupSetupIds = $this->groupSetupIds(array_filter(array_column($fieldMap, 'CAMPAIGN_GROUP', 'CAMPAIGN_ID')));

		$this->loaders = $this->compileLoaders($fieldMap, $tradingSetups, $groupSetupIds);
		$this->loadedOrders = [];

		$sourceSelect = $this->requestFieldMap($fieldMap, $sourceSelect);

		if ($this->someoneNeedTotal($this->loaders))
		{
			$sourceSelect = $this->requestTotal($sourceSelect);
		}
	}

	private function parseSelectFieldMap(array $select, array $context)
	{
		$fieldMap = [];

		foreach ($select as $name)
		{
			if (!preg_match('/^(.+)\.(AVAILABLE|COUNT|CONTEXT)$/', $name, $matches)) { continue; }
			if ($matches[2] === 'CONTEXT') { continue; }

			list($campaignId, $type, $field) = explode('.', $matches[1], 3);
			$stores = Entity\Trading\EnvironmentMapper::parseStores($type, $field);

			$field = [
				'SOURCE_TYPE' => $type,
				'SOURCE_FIELD' => $field,
				'NEED_TOTAL' => $this->needTotal($stores),
				'STORES' => $stores,
				'CONTEXT_FIELD' => $matches[1] . '.CONTEXT',
			];

			if ($matches[2] === 'AVAILABLE')
			{
				$field += [
					'CAMPAIGN_ID' => $campaignId,
					'CAMPAIGN_GROUP' => !empty($context['CAMPAIGN_GROUP'][$campaignId]) ? (array)$context['CAMPAIGN_GROUP'][$campaignId] : null,
				];
			}

			$fieldMap[$name] = $field;
		}

		return $fieldMap;
	}

	private function tradingSetups(array $campaignIds)
	{
		if (empty($campaignIds)) { return []; }

		$result = [];

		$collection = Trading\Campaign\Model::loadList([
			'filter' => [
				'=TRADING.ACTIVE' => true,
				'=ID' => $campaignIds,
			],
		]);

		foreach ($collection as $campaign)
		{
			$result[$campaign->getId()] = $campaign->getTrading();
		}

		return $result;
 	}

    private function groupSetupIds($groupCampaignIds)
    {
		$campaignGroupMap = ArrayHelper::flipMultidimensional($groupCampaignIds);

	    if (empty($campaignGroupMap)) { return []; }

		$result = [];

	    $query = Trading\Setup\Table::getList([
		    'filter' => [
			    '=TRADING_SERVICE' => Trading\Service\Manager::SERVICE_MARKETPLACE,
			    '=ACTIVE' => true,
			    '=CAMPAIGN_ID' => array_keys($campaignGroupMap),
		    ],
		    'select' => [ 'ID', 'CAMPAIGN_ID' ],
	    ]);

	    while ($row = $query->fetch())
	    {
			$mainCampaignId = $campaignGroupMap[$row['CAMPAIGN_ID']];

			if (!isset($result[$mainCampaignId])) { $result[$mainCampaignId] = []; }

			$result[$mainCampaignId][$row['CAMPAIGN_ID']] = (int)$row['ID'];
	    }

		return $result;
    }

	private function compileLoaders(array $fieldMap, array $tradingSetups, array $groupSetupIds)
	{
		$loaders = [];

		foreach ($fieldMap as $name => $field)
		{
			$loader = array_intersect_key($field, [
				'CONTEXT_FIELD' => true,
				'SOURCE_TYPE' => true,
				'SOURCE_FIELD' => true,
				'NEED_TOTAL' => true,
				'STORES' => true,
			]);

			if (isset($field['CAMPAIGN_ID'], $tradingSetups[$field['CAMPAIGN_ID']]))
			{
				/** @var Trading\Setup\Model $tradingSetup */
				$tradingSetup = $tradingSetups[$field['CAMPAIGN_ID']];

				$platform = $tradingSetup->getPlatform();
				$platform->setSetupId($tradingSetup->getId());
				$platform->setGroupSetupIds((isset($groupSetupIds[$field['CAMPAIGN_ID']]) ? $groupSetupIds[$field['CAMPAIGN_ID']] : []) + [
					$field['CAMPAIGN_ID'] => $tradingSetup->getId(),
				]);

				$environment = $tradingSetup->getEnvironment();
				$environment->getReserve()->configure([
					'STORES' => $field['STORES'],
				]);

				$orderLoader = $tradingSetup->getService()->getContainer()->get(MarketplaceCommand\ProcessingOrders::class, [
					'environment' => $environment,
					'platform' => $platform,
				]);

				$loader += [
					'ORDER_LOADER' => $orderLoader,
					'ENVIRONMENT' => $environment,
				];
			}

			$loaders[$name] = $loader;
		}

		return $loaders;
	}

	private function needTotal(array $stores)
	{
		foreach ($stores as $store)
		{
			if ($store !== TradingEntity\Common\Store::PRODUCT_FIELD_QUANTITY)
			{
				return true;
			}
		}

		return false;
	}

	private function requestFieldMap(array $fieldMap, array $sourceSelect)
	{
		foreach ($fieldMap as $field)
		{
			if (!isset($sourceSelect[$field['SOURCE_TYPE']]))
			{
				$sourceSelect[$field['SOURCE_TYPE']] = [ $field['SOURCE_FIELD'] ];
			}
			else if (!in_array($field['SOURCE_FIELD'], $sourceSelect[$field['SOURCE_TYPE']], true))
			{
				$sourceSelect[$field['SOURCE_TYPE']][] = $field['SOURCE_FIELD'];
			}
		}

		return $sourceSelect;
	}

	private function someoneNeedTotal(array $loaders)
	{
		foreach ($loaders as $loader)
		{
			if ($loader['NEED_TOTAL'])
			{
				return true;
			}
		}

		return false;
	}

	private function requestTotal(array $sourceSelect)
	{
		$type = Entity\Manager::TYPE_CATALOG_PRODUCT;
		$fields = array_keys(array_filter([
			'QUANTITY' => true,
			'QUANTITY_RESERVED' => $this->isCatalogReserveEnabled(),
			'QUANTITY_TRACE' => true,
			'CAN_BUY_ZERO' => true,
			'TIMESTAMP_X' => Entity\Catalog\Provider::useCatalogShortFields(),
		]));

		if (!isset($sourceSelect[$type]))
		{
			$sourceSelect[$type] = $fields;
			return $sourceSelect;
		}

		foreach ($fields as $name)
		{
			if (in_array($name, $sourceSelect[$type], true)) { continue; }

			$sourceSelect[$type][] = $name;
		}

		return $sourceSelect;
	}

	public function releaseQueryContext($select, $queryContext, $sourceSelect)
	{
		$this->loaders = null;
		$this->loadedOrders = null;
	}

	public function getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues)
	{
		$result = $this->collectQuantities($sourceValues, $queryContext);
		$positiveGroups = $this->onlyPositiveQuantityGroups($result);
		$needTotalGroups = array_intersect_key($positiveGroups, array_filter(ArrayHelper::column($this->loaders, 'NEED_TOTAL')));
		$total = $this->collectTotal($this->quantityGroupsElementIds($needTotalGroups), $sourceValues);

		foreach ($positiveGroups as $name => $quantities)
		{
			list($available, $contexts) = $this->compileAvailable($name, $quantities, array_intersect_key($total, $quantities));

			foreach ($available as $elementId => $quantity)
			{
				if ($quantity === $quantities[$elementId]) { continue; }

				$result[$elementId][$name] = $quantity;

				if (isset($contexts[$elementId]))
				{
					$result[$elementId][$this->loaders[$name]['CONTEXT_FIELD']] = $contexts[$elementId];
				}
			}
		}

		return $result;
	}

	private function collectQuantities(array $sourceValues, array $context)
	{
		$result = [];

		foreach ($this->loaders as $name => $loader)
		{
			$type = $loader['SOURCE_TYPE'];
			$field = $loader['SOURCE_FIELD'];

			if (isset($context['SELECT_MAP'][$type][$field]))
			{
				$field = $context['SELECT_MAP'][$type][$field];
			}

			foreach ($sourceValues as $elementId => $elementValues)
			{
				if (!isset($elementValues[$type][$field])) { continue; }

				$result[$elementId][$name] = $elementValues[$type][$field];
			}
		}

		return $result;
	}

	private function onlyPositiveQuantityGroups(array $groupValues)
	{
		$result = [];

		foreach ($this->loaders as $name => $dummy)
		{
			$group = [];

			foreach ($groupValues as $elementId => $elementValues)
			{
				if (!isset($elementValues[$name]) || !is_numeric($elementValues[$name]) || (float)$elementValues[$name] <= 0) { continue; }

				$group[$elementId] = (float)$elementValues[$name];
			}

			if (empty($group)) { continue; }

			$result[$name] = $group;
		}

		return $result;
	}

	private function quantityGroupsElementIds(array $countGroups)
	{
		$quantities = [];

		foreach ($countGroups as $groupCounts)
		{
			$quantities += $groupCounts;
		}

		return array_keys($quantities);
	}

	private function collectTotal(array $productIds, array $sourceValues)
	{
		$result = [];
		$canReserve = $this->isCatalogReserveEnabled();

		foreach ($productIds as $productId)
		{
			if (!isset($sourceValues[$productId][Entity\Manager::TYPE_CATALOG_PRODUCT])) { continue; }

			$catalogProduct = $sourceValues[$productId][Entity\Manager::TYPE_CATALOG_PRODUCT];

			if (
				$catalogProduct['QUANTITY_TRACE'] !== Catalog\ProductTable::STATUS_YES
				|| $catalogProduct['CAN_BUY_ZERO'] === Catalog\ProductTable::STATUS_YES
			)
			{
				continue;
			}

			$totalRow = [
				'AVAILABLE' => (float)$catalogProduct['QUANTITY'],
			];

			if (isset($catalogProduct['TIMESTAMP_X']))
			{
				$totalRow['TIMESTAMP_X'] = $catalogProduct['TIMESTAMP_X'];
			}

			if ($canReserve)
			{
				$totalRow['TOTAL'] = (float)($catalogProduct['QUANTITY'] + $catalogProduct['QUANTITY_RESERVED']);
			}

			$result[$productId] = $totalRow;
		}

		return $result;
	}

	private function compileAvailable($name, array $quantities, array $total)
	{
		if (!isset($this->loaders[$name]['ORDER_LOADER'], $this->loaders[$name]['ENVIRONMENT']))
		{
			$contexts = $this->compileContexts($name, $quantities, $total);
			$quantities = $this->applyTotal($quantities, $total);

			return [ $quantities, $contexts ];
		}

		$orderLoader = $this->loaders[$name]['ORDER_LOADER'];
		$environment = $this->loaders[$name]['ENVIRONMENT'];
		$productIds = array_keys($quantities);
		$reserveFilledByStore = $environment->getReserve()->filledByStore();
		$watchSiblingsProductIds = $reserveFilledByStore
			? array_keys($quantities)
			: array_keys(array_filter($total, static function(array $totalRow) { return isset($totalRow['TOTAL']) && $totalRow['TOTAL'] > 0; }));

		if (isset($this->loadedOrders[$name]))
		{
			list($processingStates, $otherStates) = $this->loadedOrders[$name];
		}
		else
		{
			list($processingStates, $otherStates) = $orderLoader->load();
			$this->loadedOrders[$name] = [ $processingStates, $otherStates ];
		}

		$waiting = $environment->getReserve()->getWaiting(array_column($processingStates, 'INTERNAL_ID'), $productIds);
		$reserves = $environment->getReserve()->getReserved(array_column($processingStates, 'INTERNAL_ID'), $productIds);
		$siblingReserves = $environment->getReserve()->getSiblingReserved(
			array_column($processingStates + $otherStates, 'INTERNAL_ID'),
			$watchSiblingsProductIds,
			$orderLoader->expireDate()
		);

		$contexts = $this->compileContexts($name, $quantities, $total, [
			'MARKET' => $reserves,
			'WAITING' => $waiting,
			'SIBLING' => $siblingReserves,
		]);

		$total = $this->decreaseTotal($total, $reserves);
		$total = $this->decreaseTotal($total, $siblingReserves);

		$quantities = $this->applyReserves($quantities, $reserves);

		if ($reserveFilledByStore)
		{
			$quantities = $this->applyReserves($quantities, $siblingReserves);
		}

		$quantities = $this->applyTotal($quantities, $total);
		$quantities = $this->applyReserves($quantities, $waiting);

		return [ $quantities, $contexts ];
	}

	private function decreaseTotal(array $total, array $reserves)
	{
		foreach ($reserves as $productId => $reserve)
		{
			if (!isset($total[$productId]['TOTAL'])) { continue; }

			$totalRow = &$total[$productId];
			$totalRow['TOTAL'] -= $reserve['QUANTITY'];

			if ($totalRow['TOTAL'] < $totalRow['AVAILABLE'])
			{
				$totalRow['AVAILABLE'] = $totalRow['TOTAL'];
			}

			unset($totalRow);
		}

		return $total;
	}

	private function applyReserves(array $quantities, array $reserves)
	{
		foreach ($quantities as $productId => &$quantity)
		{
			if (!isset($reserves[$productId])) { continue; }

			$quantity -= max(0, $reserves[$productId]['QUANTITY']);
		}
		unset($quantity);

		return $quantities;
	}

	private function applyTotal(array $quantities, array $total)
	{
		foreach ($quantities as $productId => &$quantity)
		{
			if (!isset($total[$productId]['AVAILABLE'])) { continue; }

			$limit = $total[$productId]['AVAILABLE'];

			if ($quantity > $limit)
			{
				$quantity = $limit;
			}
		}
		unset($quantity);

		return $quantities;
	}

	private function compileContexts($loaderName, array $quantities, array $total, array $reserveGroups = [])
	{
		$result = [];

		foreach ($quantities as $productId => $quantity)
		{
			$contextItem = [];

			if (isset($total[$productId]))
			{
				$contextItem['PRODUCT_QUANTITY'] = $total[$productId]['AVAILABLE'];
				$contextItem['PRODUCT_TOTAL'] = $total[$productId]['TOTAL'];

				if (isset($total[$productId]['TIMESTAMP_X']))
				{
					$contextItem['PRODUCT_TIMESTAMP_X'] = (string)$total[$productId]['TIMESTAMP_X'];
				}
			}

			foreach ($reserveGroups as $groupName => $reserves)
			{
				if (!isset($reserves[$productId])) { continue; }

				$reserve = $reserves[$productId];
				$contextItem["RESERVE_{$groupName}"] = isset($reserve['ORDER']) ? $reserve['ORDER'] : $reserve['QUANTITY'];
			}

			if (empty($contextItem)) { continue; }

			$context = [
				'item' => $contextItem + [ 'STORE_QUANTITY' => $quantity ],
				'settings' => [
					'STORES' => $this->loaders[$loaderName]['STORES'],
				],
			];

			$result[$productId] = $context;
		}

		return $result;
	}

	private function isCatalogReserveEnabled()
	{
		return Main\Config\Option::get('catalog', 'enable_reservation') !== 'N';
	}

	/** @deprecated */
	protected function getLangPrefix()
	{
		return self::getMessagePrefix();
	}
}