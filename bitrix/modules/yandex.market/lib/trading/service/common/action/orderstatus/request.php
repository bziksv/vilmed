<?php
namespace Yandex\Market\Trading\Service\Common\Action\OrderStatus;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\HttpRequest
{
	public function getOrder()
	{
		return $this->requireModel('order', Market\Api\Model\Order::class);
	}

	public function isEmulated()
	{
		return (bool)$this->getField('emulated');
	}

	public function isDownload()
	{
		return (bool)$this->getField('download');
	}

	public function isRepeat()
	{
		return (bool)$this->getField('repeat');
	}
}