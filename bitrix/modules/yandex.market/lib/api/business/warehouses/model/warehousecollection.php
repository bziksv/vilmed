<?php

namespace Yandex\Market\Api\Business\Warehouses\Model;

use Yandex\Market;

/**
 * @method Warehouse current()
 * @property  Warehouse[] $collection
 */
class WarehouseCollection extends Market\Api\Reference\Collection
{
	public static function getItemReference()
	{
		return Warehouse::class;
	}

	public function getCampaignIds()
	{
		$result = [];

		foreach ($this->collection as $warehouse)
		{
			$result[] = $warehouse->getCampaignId();
		}

		return $result;
	}

	public function getItemByCampaignId($campaignId)
	{
		$campaignId = (int)$campaignId;

		foreach ($this->collection as $warehouse)
		{
			if ($warehouse->getCampaignId() === $campaignId)
			{
				return $warehouse;
			}
		}

		return null;
	}
}