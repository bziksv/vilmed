<?php

namespace Yandex\Market\Api\Model\Order;

use Yandex\Market;

class Subsidy extends Market\Api\Reference\Model
{
	const YANDEX_CASHBACK = 'YANDEX_CASHBACK';
	const SUBSIDY = 'SUBSIDY';
	const DELIVERY = 'DELIVERY';

	public function getAmount()
	{
		return (float)$this->getField('amount');
	}

	public function getType()
	{
		return (string)$this->getField('type');
	}
}