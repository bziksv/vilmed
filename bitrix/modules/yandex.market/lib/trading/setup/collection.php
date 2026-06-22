<?php
namespace Yandex\Market\Trading\Setup;

use Yandex\Market;

/** @method getItemById($id) Model */
class Collection extends Market\Reference\Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}

	public function filterActive()
	{
		return $this->filter('ACTIVE');
	}

	public function getCampaignItems()
	{
		return $this->filter(static function(Model $trading) {
			return $trading->getBehaviorCode() !== Market\Trading\Service\Manager::BEHAVIOR_BUSINESS;
		});
	}

	public function getBusinessItem()
	{
		return $this->getByBehavior(Market\Trading\Service\Manager::BEHAVIOR_BUSINESS);
	}

	public function getItemByCampaignId($campaignId, $filter = null)
	{
		$campaignId = (int)$campaignId;

		return $this->getByField('CAMPAIGN_ID', $campaignId, $filter);
	}

	public function getActive($filter = null)
	{
		return $this->getByField('ACTIVE', Table::BOOLEAN_Y, $filter);
	}

	public function getByBehavior($behavior, $filter = null)
	{
		return $this->getByField('TRADING_BEHAVIOR', $behavior, $filter);
	}

	/** @noinspection DuplicatedCode */
	protected function getByField($field, $value, $filter = null)
	{
		$result = null;

		/** @var Model $setup*/
		foreach ($this->collection as $setup)
		{
			if ((string)$setup->getField($field) !== (string)$value) { continue; }
			if (!$this->applyFilter($setup, $filter)) { continue; }

			if ($setup->isActive())
			{
				$result = $setup;
				break;
			}

			if ($result === null)
			{
				$result = $setup;
			}
		}

		return $result;
	}

	public function injectItem(Model $trading)
	{
		foreach ($this->collection as $key => $item)
		{
			if ($item->getId() === $trading->getId())
			{
				$this->collection[$key] = $trading;
				break;
			}
		}
	}
}