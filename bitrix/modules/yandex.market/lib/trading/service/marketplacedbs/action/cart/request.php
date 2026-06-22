<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\Cart;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Marketplace\Action\Cart\Request
{
	public function getCart()
	{
		return $this->requireModel('cart', TradingService\MarketplaceDbs\Model\Cart::class);
	}
}