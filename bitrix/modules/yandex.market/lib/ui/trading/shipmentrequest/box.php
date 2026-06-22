<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class Box extends Market\Api\Reference\Model
{
	public function getBasket()
	{
		return $this->requireCollection('ITEMS', Basket::class);
	}
}