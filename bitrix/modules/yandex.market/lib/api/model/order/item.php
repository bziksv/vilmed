<?php
namespace Yandex\Market\Api\Model\Order;

use Yandex\Market;

class Item extends Market\Api\Model\Cart\Item
{
	public function getPrice()
	{
		return (float)$this->requireField('price');
	}

	public function getSubsidies()
	{
		return $this->getCollection('subsidies', Item\SubsidyCollection::class);
	}

	public function getSubsidy()
	{
		return (float)$this->getSubsidies()->getSum();
	}

	/** @noinspection PhpUnused */
	public function getFullPrice()
	{
		return $this->getPrice() + $this->getSubsidy();
	}

	public function getVat()
	{
		return (string)$this->getField('vat');
	}
}