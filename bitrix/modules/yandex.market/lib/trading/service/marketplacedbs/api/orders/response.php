<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Api\Orders;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Response extends Market\Api\Partner\Orders\Response
{
	protected function loadOrderCollection()
	{
		return $this->requireCollection('orders', TradingService\MarketplaceDbs\Model\OrderCollection::class);
	}
}
