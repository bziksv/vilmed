<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\SendOrderBoxes;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	protected $orderId;
	protected $boxes;
	protected $allowRemove = false;

	public function getPath()
	{
		return sprintf(
			'/v2/campaigns/%s/orders/%s/boxes.json',
			$this->getCampaignId(),
			$this->getOrderId()
		);
	}

	public function getQuery()
	{
		return [
			'boxes' => $this->getBoxes(),
			'allowRemove' => $this->getAllowRemove(),
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

	public function buildResponse($data)
	{
		return new Response($data);
	}

	public function setOrderId($orderId)
	{
		$this->orderId = $orderId;
	}

	public function getOrderId()
	{
		Market\Reference\Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	public function setBoxes(array $boxes)
	{
		$this->boxes = $boxes;
	}

	public function getBoxes()
	{
		Market\Reference\Assert::notNull($this->boxes, 'boxes');

		return $this->boxes;
	}

	public function setAllowRemove($allowRemove)
	{
		$this->allowRemove = (bool)$allowRemove;
	}

	public function getAllowRemove()
	{
		return $this->allowRemove;
	}
}