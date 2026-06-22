<?php
namespace Yandex\Market\Api\Business\OfferCards\Update;

use Yandex\Market\Api\Reference\Collection;

class OfferResultCollection extends Collection
{
    public static function getItemReference()
    {
        return OfferResult::class;
    }
}