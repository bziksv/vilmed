<?php
namespace Yandex\Market\Trading\Service\Turbo\Model;

use Yandex\Market;

class Order extends Market\Api\Model\Order
{
	public function getUser()
	{
		return $this->requireModel('user', Order\User::class);
	}
}