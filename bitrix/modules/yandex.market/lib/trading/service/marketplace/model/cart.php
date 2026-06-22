<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model;

use Yandex\Market;

class Cart extends Market\Api\Model\Cart
{
	public function getItems()
	{
		return $this->requireCollection('items', Cart\ItemCollection::class);
	}
}