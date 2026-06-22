<?php
namespace Yandex\Market\Api\Business\OfferMappings\UnArchive;

use Yandex\Market\Api;

class Response extends Api\Partner\Reference\Response
{
    /** @return string[] */
    public function getNotUnarchivedOfferIds()
    {
        return (array)$this->getField('result.notUnarchivedOfferIds');
    }
}