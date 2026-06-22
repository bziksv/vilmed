<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\OrderStatus;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Marketplace\Action\OrderStatus\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\MarketplaceDbs\Model\Order::class);
	}
}