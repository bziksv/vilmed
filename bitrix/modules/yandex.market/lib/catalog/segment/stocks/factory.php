<?php
namespace Yandex\Market\Catalog\Segment\Stocks;

use Yandex\Market\Catalog\Endpoint;
use Yandex\Market\Catalog\Segment;
use Yandex\Market\Export\Param;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;

class Factory implements Segment\Factory
{
	use Concerns\HasMessage;

	public function businessConfig(Business\Model $business)
	{
		return null;
	}

	public function campaignConfigs(Business\Model $business)
	{
		$result = [];

		/** @var Campaign\Model $campaign */
		foreach ($business->getCampaignCollection() as $campaign)
		{
			if ($campaign->getPlacement() === Campaign\Placement::FBY) { continue; }

			$externalSettings = $campaign->getExternalSettings();
			$groupPrimary = $externalSettings->getWarehouseGroupPrimary();

			if ($groupPrimary > 0)
			{
				if ($groupPrimary !== $campaign->getId()) { continue; }

				$groupName = $externalSettings->getWarehouseGroupName() ?: $campaign->getName();

				$result[] = new Segment\CampaignConfig(new Format(), $campaign->getId(), self::getMessage('GROUP', [
					'#NAME#' => $groupName,
				], $groupName));
			}
			else
			{
				$result[] = new Segment\CampaignConfig(new Format(), $campaign->getId(), $campaign->getName(), $campaign->getPlacement());
			}
		}

		return $result;
	}

	public function endpoints(Business\Model $business, Segment\Collection $segmentCollection)
	{
		$result = [];
		$campaignCollection = $business->getCampaignCollection();

		/** @var Segment\Model $campaignSegment */
		foreach ($segmentCollection->getCampaignItems() as $campaignSegment)
		{
			$campaignId = $campaignSegment->getCampaignId();
			$campaign = $campaignCollection->getItemById($campaignId);

			if ($campaign === null) { continue; }

			$groupPrimary = $campaign->getExternalSettings()->getWarehouseGroupPrimary();

			if ($groupPrimary > 0)
			{
				$endpointKey = $groupPrimary;
				$driver = new Endpoint\Stocks($groupPrimary, array_diff($campaignCollection->getWarehouseGroupCampaignIds($groupPrimary), [ $groupPrimary ]));
			}
			else
			{
				$endpointKey = $campaignId;
				$driver = new Endpoint\Stocks($campaignId);
			}

            $result[$endpointKey] = new Endpoint\Endpoint($driver, new Param\TagBundle(
                (new Format(true))->getTag(),
                $campaignSegment->getParamCollection()->getTagMap()
            ));
		}

		return array_values($result);
	}
}