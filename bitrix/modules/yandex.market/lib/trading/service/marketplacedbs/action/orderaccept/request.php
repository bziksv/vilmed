<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\OrderAccept;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Marketplace\Action\OrderAccept\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\MarketplaceDbs\Model\Order::class);
	}
}