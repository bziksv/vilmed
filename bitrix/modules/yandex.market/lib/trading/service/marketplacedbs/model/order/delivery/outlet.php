<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model\Order\Delivery;

use Yandex\Market;

class Outlet extends Market\Api\Reference\Model
{
	/** @return string|null */
	public function getCode()
	{
		return $this->requireField('code');
	}
}