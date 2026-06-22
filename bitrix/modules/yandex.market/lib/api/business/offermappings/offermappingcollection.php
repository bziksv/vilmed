<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api\Reference;

/** @property OfferMapping[] $collection */
class OfferMappingCollection extends Reference\Collection
{
    public static function getItemReference()
    {
        return OfferMapping::class;
    }

    public function getOfferIds()
    {
        $result = [];

        foreach ($this->collection as $offerMapping)
        {
            $result[] = $offerMapping->getOffer()->getOfferId();
        }

        return $result;
    }

    public function getItemByOfferId($offerId)
    {
        $offerId = (string)$offerId;

        foreach ($this->collection as $offerMapping)
        {
            if ($offerMapping->getOffer()->getOfferId() === $offerId)
            {
                return $offerMapping;
            }
        }

        return null;
    }
}