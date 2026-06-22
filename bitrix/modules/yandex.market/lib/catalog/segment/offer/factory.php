<?php
namespace Yandex\Market\Catalog\Segment\Offer;

use Yandex\Market\Catalog\Endpoint;
use Yandex\Market\Catalog\Segment;
use Yandex\Market\Export\Param\TagBundle;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;

class Factory implements Segment\Factory
{
	public function businessConfig(Business\Model $business)
	{
		return new Segment\BusinessConfig(new BusinessFormat());
	}

	public function campaignConfigs(Business\Model $business)
	{
        $result = [];

        /** @var Campaign\Model $campaign */
        foreach ($business->getCampaignCollection() as $campaign)
        {
            $result[] = new Segment\CampaignConfig(new CampaignFormat(), $campaign->getId(), $campaign->getName(), $campaign->getPlacement());
        }

        return $result;
	}

	public function endpoints(Business\Model $business, Segment\Collection $segmentCollection)
	{
		return array_filter(array_merge(
            $this->offerEndpoints($business, $segmentCollection->getBusinessItem()),
            $this->termsEndpoints($business, $segmentCollection)
        ));
	}

    private function offerEndpoints(Business\Model $business, Segment\Model $businessSegment = null)
    {
        if ($businessSegment === null) { return []; }

        $termsTags = $this->termsTags();
        $map = $businessSegment->getParamCollection()->getTagMap()->cloneWithout($termsTags);

        if ($map->isEmpty()) { return []; }

        $format = (new BusinessFormat())->getTag()->cloneWithout($termsTags);

        return [
            new Endpoint\Endpoint(
                new Endpoint\Offer($business->getId()),
                new TagBundle($format, $map)
            ),
        ];
    }

    private function termsEndpoints(Business\Model $business, Segment\Collection $segmentCollection)
    {
        $result = [];
        $termsTags = $this->termsTags();
        $businessSegment = $segmentCollection->getBusinessItem();
        $commonMap = $businessSegment !== null
            ? $businessSegment->getParamCollection()->getTagMap()->cloneOnly($termsTags)
            : null;

        if ($commonMap !== null && !$commonMap->isEmpty())
        {
	        /** @var Campaign\Model $campaign */
            foreach ($business->getCampaignCollection() as $campaign)
            {
                $campaignSegment = $segmentCollection->getCampaignItem($campaign->getId());
				$campaignMap = $campaignSegment !== null
                    ? $campaignSegment->getParamCollection()->getTagMap()->merge($commonMap)
                    : $commonMap;

                $result[] = new Endpoint\Endpoint(
                    new Endpoint\Terms($campaign->getId()),
                    new TagBundle((new CampaignFormat())->getTag(), $campaignMap),
                    'quantum'
                );
            }
        }
        else
        {
            /** @var Segment\Model $segment */
            foreach ($segmentCollection as $segment)
            {
                if (!$segment->isCampaign()) { continue; }

                $map = $segment->getParamCollection()->getTagMap();

                if (!$map->hasAny($termsTags)) { continue; }

                $result[] = new Endpoint\Endpoint(
                    new Endpoint\Terms($segment->getCampaignId()),
                    new TagBundle((new CampaignFormat())->getTag(), $map->cloneOnly($termsTags)),
                    'quantum'
                );
            }
        }

        return $result;
    }

    private function termsTags()
    {
        return [
            'min-quantity',
            'step-quantity',
        ];
    }
}