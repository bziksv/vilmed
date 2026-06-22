<?php
namespace Yandex\Market\Api\Model;

use Yandex\Market;

class Cart extends Market\Api\Reference\Model
{
	public function getCurrency()
	{
		return (string)$this->requireField('currency');
	}

	public function hasDelivery()
	{
		return $this->hasField('delivery');
	}

	public function getDelivery()
	{
		return $this->requireModel('delivery', Cart\Delivery::class);
	}

	public function getItems()
	{
		return $this->requireCollection('items', Cart\ItemCollection::class);
	}
}