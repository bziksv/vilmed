<?php
namespace Yandex\Market\Api\Partner\Order;

use Yandex\Market;

class Response extends Market\Api\Partner\Reference\Response
{
	public function getOrder()
	{
		return $this->requireModel('order', Market\Api\Model\Order::class);
	}
}