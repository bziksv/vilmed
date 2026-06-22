<?php
namespace Yandex\Market\Api\Campaigns\HiddenOffers\Post;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;

class Request extends Api\Partner\Reference\Request
{
	public function getPath()
	{
		return "/campaigns/{$this->getCampaignId()}/hidden-offers";
	}

    public function getMethod()
    {
        return HttpClient::HTTP_POST;
    }

    public function getQueryFormat()
    {
        return static::DATA_TYPE_JSON;
    }

	public function setHiddenOffers(array $offerIds)
	{
		$this->query['hiddenOffers'] = array_map(
            static function($offerId) { return [ 'offerId' => (string)$offerId ]; },
            $offerIds
        );
	}
}