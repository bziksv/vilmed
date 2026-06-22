<?php
namespace Yandex\Market\Api\Model\Order\Item;

use Yandex\Market\Api\Model\Order;

/** @property Subsidy[] $collection */
class SubsidyCollection extends Order\SubsidyCollection
{
	public static function getItemReference()
	{
		return Subsidy::class;
	}
}
