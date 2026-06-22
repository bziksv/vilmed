<?php
namespace Yandex\Market\Ui\Trading;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Catalog;
use Yandex\Market\SalesBoost;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Setup;
use Yandex\Market\Trading\Service;

class MenuCompiler
{
	use Concerns\HasMessage;

	/** @var array */
	private $items;
	private $changed = false;

	public function installBusiness($businessId, $businessName)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null)
		{
			$this->changed = true;

			$otherKey = $this->businessKey($items, 0);
			$newItem = [
				'ID' => $businessId,
				'NAME' => $businessName,
				'BUSINESS_BEHAVIOR' => false,
				'BEHAVIOR' => [],
			];

			if ($otherKey !== null)
			{
				array_splice($items, $otherKey, 0, [ $newItem ]);
			}
			else
			{
				$items[] = $newItem;
			}
		}
		else if ($items[$businessKey]['NAME'] !== $businessName)
		{
			$this->changed = true;
			$items[$businessKey]['NAME'] = $businessName;
		}

		$this->items =  $items;

		return $items;
	}

	public function uninstallBusiness($businessId)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null) { return $items; }

		$this->changed = true;

		array_splice($items, $businessKey, 1);
		$this->items = $items;

		return $items;
	}

	public function injectBusinessBehavior($businessId)
	{
		return $this->injectBehavior($businessId, 'BUSINESS');
	}

	public function ejectBusinessBehavior($businessId)
	{
		return $this->ejectBehavior($businessId, 'BUSINESS');
	}

	public function injectCampaignBehavior($businessId)
	{
		return $this->injectBehavior($businessId, 'CAMPAIGN');
	}

	public function ejectCampaignBehavior($businessId)
	{
		return $this->ejectBehavior($businessId, 'CAMPAIGN');
	}

	private function injectBehavior($businessId, $type)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null)
		{
			throw new Main\SystemException("install business menu {$businessId} before inject {$type}");
		}

		if ($items[$businessKey]["{$type}_BEHAVIOR"]) { return $items; }

		$items[$businessKey]["{$type}_BEHAVIOR"] = true;

		$this->changed = true;
		$this->items = $items;

		return $items;
	}

	private function ejectBehavior($businessId, $type)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null) { return $items; }

		if (!$items[$businessKey]["{$type}_BEHAVIOR"]) { return $items; }

		$items[$businessKey]["{$type}_BEHAVIOR"] = false;

		$this->changed = true;
		$this->items = $items;

		return $items;
	}

	public function injectTrading($businessId, $tradingBehavior)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null)
		{
			throw new Main\SystemException("install business menu {$businessId} before inject trading");
		}

		if (!in_array($tradingBehavior, $items[$businessKey]['BEHAVIOR'], true))
		{
			$items[$businessKey]['BEHAVIOR'][] = $tradingBehavior;

			$this->changed = true;
			$this->items = $items;
		}

		return $items;
	}

	public function ejectTrading($businessId, $tradingBehavior)
	{
		$items = $this->items();
		$businessKey = $this->businessKey($items, $businessId);

		if ($businessKey === null) { return $items; }

		$behaviorIndex = array_search($tradingBehavior, $items[$businessKey]['BEHAVIOR'], true);

		if ($behaviorIndex === false) { return $items; }

		unset($items[$businessKey]['BEHAVIOR'][$behaviorIndex]);
		$items[$businessKey]['BEHAVIOR'] = array_values($items[$businessKey]['BEHAVIOR']);

		$this->changed = true;
		$this->items = $items;

		return $items;
	}

	public function extractOther()
	{
		$items = $this->items();
		$otherKey = $this->businessKey($items, 0);

		if ($otherKey === null || $this->hasUnknown($items)) { return $items; }

		array_splice($items, $otherKey, 1);

		$this->changed = true;
		$this->items = $items;

		return $this->items;
	}

	private function businessKey(array $items, $businessId)
	{
		foreach ($items as $itemKey => $item)
		{
			if ($item['ID'] === $businessId)
			{
				return $itemKey;
			}
		}

		return null;
	}

	public function rebuild()
	{
		$items = $this->rebuildKnown();

		if ($this->hasUnknown($items))
		{
			$items[] = $this->describeOther();
		}

		$this->items = $items;
		$this->changed = true;

		return $items;
	}

	private function rebuildKnown()
	{
		$items = [];
		$businesses = Business\Model::loadList();

		foreach ($businesses as $business)
		{
			if ($this->isEmptyBusiness($business)) { continue; }

			$items[] = $this->describeBusiness($business);
		}

		return $items;
	}

	private function isEmptyBusiness(Business\Model $business)
	{
		return (
			$business->getCatalog() === null
			&& $business->getTradingCollection()->count() === 0
			&& $business->getSalesBoostCollection()->count() === 0
		);
	}

	private function describeBusiness(Business\Model $business)
	{
		$campaignCollection = $business->getCampaignCollection();
		$tradingCollection = $business->getTradingCollection();

		if ($campaignCollection->count() > 0)
		{
			$behaviors = array_reduce($campaignCollection->asArray(), static function(array $carry, Campaign\Model $setup) {
				$behavior = $setup->getTradingBehavior();

				if (!in_array($behavior, $carry, true))
				{
					$carry[] = $behavior;
				}

				return $carry;
			}, []);
		}
		else
		{
			$behaviors = array_reduce($tradingCollection->asArray(), static function(array $carry, Setup\Model $setup) {
				$behavior = $setup->getBehaviorCode();

				if ($behavior !== Service\Manager::BEHAVIOR_BUSINESS && !in_array($behavior, $carry, true) && $setup->isActive())
				{
					$carry[] = $behavior;
				}

				return $carry;
			}, []);
		}

		return [
			'ID' => $business->getId(),
			'NAME' => $business->getName(),
			'BUSINESS_BEHAVIOR' => $tradingCollection->getByBehavior(Service\Manager::BEHAVIOR_BUSINESS) !== null,
			'CAMPAIGN_BEHAVIOR' => $tradingCollection->getCampaignItems()->filterActive()->count() > 0,
			'BEHAVIOR' => $behaviors,
		];
	}

	private function describeOther()
	{
		return [
			'ID' => 0,
			'NAME' => self::getMessage('OTHER'),
			'BUSINESS_BEHAVIOR' => false,
			'CAMPAIGN_BEHAVIOR' => false,
			'BEHAVIOR' => [],
		];
	}

	private function hasUnknown(array $items)
	{
		$ids = array_diff(array_column($items, 'ID'), [ 0 ]);
		$filter = [
			'filter' => !empty($ids) ? [ '!=BUSINESS_ID' => $ids ] : [],
			'select' => [ 'ID' ],
		];

		return (
			Setup\Table::getRow($filter)
			|| Catalog\Setup\Table::getRow($filter)
			|| SalesBoost\Setup\Table::getRow($filter)
		);
	}

	public function save()
	{
		if ($this->items === null || !$this->changed) { return; }

		Menu::store($this->items);
		$this->changed = false;
	}

	private function items()
	{
		if ($this->items !== null) { return $this->items; }

		$this->items = Menu::stored();

		return $this->items;
	}
}