<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api\Reference;

class OfferCampaign extends Reference\Model
{
    const PUBLISHED = 'PUBLISHED';
    const CHECKING = 'CHECKING';
    const DISABLED_BY_PARTNER = 'DISABLED_BY_PARTNER';
    const DISABLED_AUTOMATICALLY = 'DISABLED_AUTOMATICALLY';
    const REJECTED_BY_MARKET = 'REJECTED_BY_MARKET';
    const CREATING_CARD = 'CREATING_CARD';
    const NO_CARD = 'NO_CARD';
    const NO_STOCKS = 'NO_STOCKS';
    const ARCHIVED = 'ARCHIVED';
    
    public function getCampaignId()
    {
        return (int)$this->requireField('campaignId');
    }

    public function getStatus()
    {
        return (string)$this->getField('status');
    }
}