<?php
namespace Yandex\Market\Api\Business\OfferMappings\Update;

use Yandex\Market\Api\Reference\Model;

class OfferResult extends Model
{
    public function getOfferId()
    {
        return (string)$this->requireField('offerId');
    }

    public function getErrors()
    {
        return $this->getCollection('errors', OfferErrorCollection::class);
    }

    public function getWarnings()
    {
        return $this->getCollection('warnings', OfferErrorCollection::class);
    }
}