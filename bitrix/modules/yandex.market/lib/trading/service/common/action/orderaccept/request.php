<?php
namespace Yandex\Market\Trading\Service\Common\Action\OrderAccept;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\Cart\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', Market\Api\Model\Order::class);
	}

	public function getCart()
	{
		return $this->getOrder();
	}

	public function isDownload()
	{
		return (bool)$this->getField('download');
	}
}