<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\ApiMode;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	protected $apiMode;

	public function getPath()
	{
		return '/v2/campaigns/' . $this->getCampaignId() . '/api-mode/orders.json';
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_POST;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function getQuery()
	{
		return [
			'ordersApiModeType' => $this->getApiMode(),
		];
	}

	public function buildResponse($data)
	{
		return new Response($data + [
			'status' => Response::STATUS_OK,
		]);
	}

	public function setApiMode($mode)
	{
		$this->apiMode = $mode;
	}

	public function getApiMode()
	{
		Market\Reference\Assert::notNull($this->apiMode, 'apiMode');

		return $this->apiMode;
	}
}