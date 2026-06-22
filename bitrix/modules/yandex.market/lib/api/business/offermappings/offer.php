<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api\Reference;

class Offer extends Reference\Model
{
    public function getOfferId()
    {
        return (string)$this->requireField('offerId');
    }

    public function getArchived()
    {
        return (bool)$this->getField('archived');
    }

    public function getCampaigns()
    {
        return $this->getCollection('campaigns', OfferCampaignCollection::class);
    }

    public function isArchived($campaignId)
    {
        $campaignId = (int)$campaignId;

        if ($campaignId === 0)
        {
            return $this->getArchived();
        }

        $offerCampaign = $this->getCampaigns()->getItemByCampaignId($campaignId);

        if ($offerCampaign === null)
        {
            return false;
        }

        return ($offerCampaign->getStatus() === OfferCampaign::DISABLED_BY_PARTNER);
    }
}