<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;

/** @method Response execute() */
class Request extends Api\Partner\Reference\BusinessRequest
{
	const OFFER_IDS_LIMIT = 200;

	public function getPath()
	{
		return "/businesses/{$this->getBusinessId()}/offer-cards";
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
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