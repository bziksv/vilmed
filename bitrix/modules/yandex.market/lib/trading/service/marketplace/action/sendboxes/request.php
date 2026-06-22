<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\SendBoxes;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Reference\Action\DataRequest
{
	public function getOrderId()
	{
		return (int)$this->requireField('orderId');
	}

	public function getOrderNumber()
	{
		return (string)$this->requireField('orderNum');
	}

	public function getShipmentId()
	{
		return $this->getField('shipmentId');
	}

	public function getBoxes()
	{
		return (array)$this->requireField('boxes');
	}
}