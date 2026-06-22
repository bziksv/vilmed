<?php

namespace Yandex\Market\Api\Business\Warehouses\Model;

use Yandex\Market;

/**
 * @method WarehouseGroup current()
 * @property WarehouseGroup[] $collection
 */
class WarehouseGroupCollection extends Market\Api\Reference\Collection
{
	public static function getItemReference()
	{
		return WarehouseGroup::class;
	}

	public function getItemByCampaignId($campaignId)
	{
		$campaignId = (int)$campaignId;

		foreach ($this->collection as $group)
		{
			if ($group->getMainWarehouse()->getCampaignId() === $campaignId)
			{
				return $group;
			}

			if ($group->getWarehouses()->getItemByCampaignId($campaignId) !== null)
			{
				return $group;
			}
		}

		return null;
	}
}