<?php
namespace Yandex\Market\Api\Campaigns\Offers\Update;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;

class Request extends Api\Partner\Reference\Request
{
	public function getPath()
	{
		return "/campaigns/{$this->getCampaignId()}/offers/update.json";
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function getQuery()
	{
		Assert::notNull($this->query['offers'], 'offers');

		return $this->query;
	}

	public function setOffers(array $offers)
	{
		$this->query['offers'] = $offers;
	}
}