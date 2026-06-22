<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api\Reference;

class OfferMapping extends Reference\Model
{
    public function getOffer()
    {
        return $this->requireModel('offer', Offer::class);
    }

    public function getMapping()
    {
        return $this->anyModel('mapping', Mapping::class);
    }
}