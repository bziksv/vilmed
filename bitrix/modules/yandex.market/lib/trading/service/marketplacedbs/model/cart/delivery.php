<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model\Cart;

use Yandex\Market;

class Delivery extends Market\Api\Model\Cart\Delivery
{
	public function getAddress()
	{
		return $this->getModel('address', Delivery\Address::class);
	}
}