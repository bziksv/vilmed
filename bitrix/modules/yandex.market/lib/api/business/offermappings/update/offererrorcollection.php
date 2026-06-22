<?php
namespace Yandex\Market\Api\Business\OfferMappings\Update;

use Yandex\Market\Api\Reference\Collection;

class OfferErrorCollection extends Collection
{
    public static function getItemReference()
    {
        return OfferError::class;
    }
}