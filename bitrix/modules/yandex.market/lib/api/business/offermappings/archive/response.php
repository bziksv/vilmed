<?php
namespace Yandex\Market\Api\Business\OfferMappings\Archive;

use Yandex\Market\Api;

class Response extends Api\Partner\Reference\Response
{
    public function getNotArchivedOffers()
    {
        return $this->getCollection('result.notArchivedOffers', NotArchivedOfferCollection::class);
    }
}