<?php
namespace Yandex\Market\Api\Business\OfferMappings\Archive;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api\Partner\Reference;
use Yandex\Market\Reference\Assert;

/** @method Response execute() */
class Request extends Reference\BusinessRequest
{
    public function getPath()
    {
        return "/businesses/{$this->getBusinessId()}/offer-mappings/archive";
    }

    public function getMethod()
    {
        return HttpClient::HTTP_POST;
    }

    public function getQuery()
    {
        Assert::notEmpty($this->query['offerIds'], 'query[offerIds]');

        return $this->query;
    }

    public function getQueryFormat()
    {
        return static::DATA_TYPE_JSON;
    }

    public function setOfferIds(array $offerIds)
    {
        $this->query['offerIds'] = array_map(
            static function($offerId) { return (string)$offerId; },
            $offerIds
        );
    }

    public function buildResponse($data)
    {
        return new Response($data);
    }
}