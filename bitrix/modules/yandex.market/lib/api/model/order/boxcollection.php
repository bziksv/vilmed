<?php

namespace Yandex\Market\Api\Model\Order;

use Yandex\Market;

class BoxCollection extends Market\Api\Reference\Collection
{
	public static function getItemReference()
	{
		return Box::class;
	}
}