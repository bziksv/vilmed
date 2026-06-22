<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Yandex\Market\Api;

class Response extends Api\Partner\Reference\Response
{
    public function getOfferMappings()
    {
        return $this->requireCollection('result.offerMappings', OfferMappingCollection::class);
    }

    public function getPaging()
    {
        return $this->anyModel('result.paging', Api\Model\Paging::class);
    }
}