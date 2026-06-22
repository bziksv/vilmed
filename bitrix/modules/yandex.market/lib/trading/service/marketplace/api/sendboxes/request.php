<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\SendBoxes;

use Bitrix\Main;
use Yandex\Market\Api\Partner\Reference;
use Yandex\Market\Api\Reference\Validator;
use Yandex\Market\Reference\Assert;

class Request extends Reference\Request
{
	protected $orderId;
	protected $shipmentId;
	protected $boxes;

	public function getPath()
	{
		return '/v2/campaigns/' . $this->getCampaignId() . '/orders/' . $this->getOrderId() .'/delivery/shipments/' . $this->getShipmentId() .'/boxes.json';
	}

	public function getQuery()
	{
		return [
			'boxes' => $this->getBoxes()
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

	public function setOrderId($orderId)
	{
		$this->orderId = $orderId;
	}

	public function getOrderId()
	{
		Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	public function setShipmentId($shipmentId)
	{
		$this->shipmentId = $shipmentId;
	}

	public function getShipmentId()
	{
		return (string)($this->shipmentId ?: 1);
	}

	public function setBoxes($boxes)
	{
		$this->boxes = $boxes;
	}

	public function getBoxes()
	{
		Assert::notNull($this->boxes, 'boxes');

		return (array)$this->boxes;
	}

	protected function validationQueue()
	{
		$queue = parent::validationQueue();
		$queue->add(new Validator\RequiredKeys([
			'result.boxes',
		]));

		return $queue;
	}
}