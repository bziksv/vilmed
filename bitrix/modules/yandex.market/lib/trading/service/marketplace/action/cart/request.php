<?php
namespace Yandex\Market\Trading\Service\Marketplace\Action\Cart;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\Cart\Request
{
	public function getCart()
	{
		return $this->requireModel('cart', TradingService\Marketplace\Model\Cart::class);
	}
}