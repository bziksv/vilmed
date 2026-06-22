<?php
namespace Yandex\Market\Trading\Service\Common\Action\Cart;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\HttpRequest
{
	public function getCart()
	{
		return $this->requireModel('cart', Market\Api\Model\Cart::class);
	}
}