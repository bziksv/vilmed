<?php

namespace Yandex\Market\Trading\Service\Marketplace\Api\SendItems;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	protected $orderId;
	protected $items;

	public function getPath()
	{
		return sprintf(
			'/v2/campaigns/%s/orders/%s/items.json',
			$this->getCampaignId(),
			$this->getOrderId()
		);
	}

	public function getQuery()
	{
		return [
			'items' => $this->getItems(),
		];
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_PUT;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function setOrderId($trackCode)
	{
		$this->orderId = $trackCode;
	}

	public function getOrderId()
	{
		Market\Reference\Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	public function getItems()
	{
		Market\Reference\Assert::notNull($this->items, 'items');

		return $this->items;
	}

	public function setItems($items)
	{
		$this->items = $items;
	}
}