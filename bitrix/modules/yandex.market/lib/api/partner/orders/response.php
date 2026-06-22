<?php
namespace Yandex\Market\Api\Partner\Orders;

use Yandex\Market;

class Response extends Market\Api\Partner\Reference\Response
{
	public function getOrderCollection()
	{
		$orderCollection = $this->loadOrderCollection();
		$orderCollection->setPager($this->getPager());

		return $orderCollection;
	}

	protected function loadOrderCollection()
	{
		return $this->requireCollection('orders', Market\Api\Model\OrderCollection::class);
	}

	public function getPager()
	{
		return $this->anyModel('pager', Market\Api\Model\Pager::class);
	}
}
