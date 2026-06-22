<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model\Cart;

use Yandex\Market;

class Item extends Market\Api\Model\Cart\Item
{
	public function getFeedId()
	{
		return (int)$this->requireField('feedId');
	}

	public function getPartnerWarehouseId()
	{
		return (string)$this->requireField('partnerWarehouseId');
	}
}