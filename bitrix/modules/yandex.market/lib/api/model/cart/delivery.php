<?php
namespace Yandex\Market\Api\Model\Cart;

use Yandex\Market;

class Delivery extends Market\Api\Reference\Model
{
	public function getRegion()
	{
		return $this->requireModel('region', Market\Api\Model\Region::class);
	}
}