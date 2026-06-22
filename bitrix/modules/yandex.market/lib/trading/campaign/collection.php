<?php
namespace Yandex\Market\Trading\Campaign;

use Yandex\Market\Api;
use Yandex\Market\Reference\Storage;

/**
 * @method Model getItemById($id)
 * @property Model[] $collection
 */
class Collection extends Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}

	public static function fromApi(
		Api\Campaigns\Model\CampaignCollection $apiCampaigns,
		Api\Business\Warehouses\Model\WarehouseGroupCollection $warehouseGroupCollection
	)
	{
		$collection = new static();

		/** @var Api\Campaigns\Model\Campaign $apiCampaign */
		foreach ($apiCampaigns as $apiCampaign)
		{
			if ($apiCampaign->getPlacementType() === null) { continue; }

			$campaign = Model::fromApi(
				$apiCampaign,
				$warehouseGroupCollection->getItemByCampaignId($apiCampaign->getId())
			);

			$collection->addItem($campaign);
			$campaign->setParentCollection($collection);
		}

		return $collection;
	}

	public function getFirstWithTrading()
	{
		foreach ($this->collection as $model)
		{
			if ($model->getTradingId() > 0)
			{
				return $model;
			}
		}

		return null;
	}

	public function getWarehouseGroupCampaignIds($primaryCampaignId)
	{
		$primaryCampaignId = (int)$primaryCampaignId;

		if ($primaryCampaignId <= 0) { return []; }

		$campaignIds = [];

		foreach ($this->collection as $model)
		{
			if ($model->getExternalSettings()->getWarehouseGroupPrimary() === $primaryCampaignId)
			{
				$campaignIds[] = $model->getId();
			}
		}

		return $campaignIds;
	}
}