<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api\Reference;

/** @property OfferCampaign[] $collection */
class OfferCampaignCollection extends Reference\Collection
{
    public static function getItemReference()
    {
        return OfferCampaign::class;
    }

    public function getItemByCampaignId($campaignId)
    {
        $campaignId = (int)$campaignId;

        foreach ($this->collection as $offerCampaign)
        {
            if ($offerCampaign->getCampaignId() === $campaignId)
            {
                return $offerCampaign;
            }
        }

        return null;
    }
}