<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\OrderCancellationNotify;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\HttpRequest
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\MarketplaceDbs\Model\Order::class);
	}
}