<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model;

use Yandex\Market\Trading\Service as TradingService;

class Cart extends TradingService\Marketplace\Model\Cart
{
	public function getDelivery()
	{
		return $this->requireModel('delivery', Cart\Delivery::class);
	}
}