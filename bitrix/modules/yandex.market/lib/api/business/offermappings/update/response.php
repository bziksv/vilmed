<?php
namespace Yandex\Market\Api\Business\OfferMappings\Update;

use Yandex\Market\Api;

class Response extends Api\Partner\Reference\Response
{
    public function getResults()
    {
        return $this->getCollection('results', OfferResultCollection::class);
    }
}