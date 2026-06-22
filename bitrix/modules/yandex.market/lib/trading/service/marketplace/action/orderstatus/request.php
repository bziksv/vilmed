<?php
namespace Yandex\Market\Trading\Service\Marketplace\Action\OrderStatus;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\OrderStatus\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\Marketplace\Model\Order::class);
	}
}