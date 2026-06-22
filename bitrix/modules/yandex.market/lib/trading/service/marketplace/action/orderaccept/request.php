<?php
namespace Yandex\Market\Trading\Service\Marketplace\Action\OrderAccept;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\OrderAccept\Request
{
	public function getOrder()
	{
		return $this->requireModel('order', TradingService\Marketplace\Model\Order::class);
	}
}