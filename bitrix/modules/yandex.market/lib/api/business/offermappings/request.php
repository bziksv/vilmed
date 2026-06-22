<?php
namespace Yandex\Market\Api\Business\OfferMappings;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;

/** @method Response execute() */
class Request extends Api\Partner\Reference\BusinessRequest
{
    const OFFERS_LIMIT = 200;

    public function getPath()
    {
        return "/businesses/{$this->getBusinessId()}/offer-mappings";
    }

    public function getMethod()
    {
        return HttpClient::HTTP_POST;
    }

    public function getQueryFormat()
    {
        return static::DATA_TYPE_JSON;
    }

    public function setLimit($limit)
    {
        $this->query['limit'] = $limit;
    }

    public function setArchived($archived)
    {
        $this->query['archived'] = (bool)$archived;
    }

    public function setOfferIds(array $offerIds)
    {
        $this->query['offerIds'] = array_map(
            static function($offerId) { return (string)$offerId; },
            $offerIds
        );
    }

    /** @param string $pageToken */
    public function setPageToken($pageToken)
    {
        $this->urlQuery['pageToken'] = $pageToken;
    }

    public function buildResponse($data)
    {
        return new Response($data);
    }
}