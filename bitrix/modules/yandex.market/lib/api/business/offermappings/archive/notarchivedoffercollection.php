<?php
namespace Yandex\Market\Api\Business\OfferMappings\Archive;

use Yandex\Market\Api\Reference;

class NotArchivedOfferCollection extends Reference\Collection
{
    public static function getItemReference()
    {
        return NotArchivedOffer::class;
    }
}