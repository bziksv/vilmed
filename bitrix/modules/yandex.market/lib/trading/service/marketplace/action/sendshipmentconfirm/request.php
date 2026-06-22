<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\SendShipmentConfirm;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Reference\Action\DataRequest
{
	public function getShipmentId()
	{
		return (int)$this->requireField('shipmentId');
	}

	public function getExternalShipmentId()
	{
		return (string)$this->requireField('externalShipmentId');
	}

	public function getOrderIds()
	{
		return (array)$this->requireField('orderIds');
	}
}