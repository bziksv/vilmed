<?php
namespace Yandex\Market\Trading\Service\Turbo\Action\OrderStatus;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\OrderStatus\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\Turbo\Model\Order::class);
	}
}