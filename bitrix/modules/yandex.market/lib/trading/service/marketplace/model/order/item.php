<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model\Order;

use Yandex\Market;

class Item extends Market\Api\Model\Order\Item
{
	public function getFeedId()
	{
		return (int)$this->requireField('feedId');
	}

	public function getPartnerWarehouseId()
	{
		return (string)$this->requireField('partnerWarehouseId');
	}

	/** @return string|null */
	public function getBundleId()
	{
		return $this->getField('bundleId');
	}

	public function getPromos()
	{
		return $this->getCollection('promos', Item\PromoCollection::class);
	}

	public function getInstances()
	{
		return $this->getCollection('instances', Item\InstanceCollection::class);
	}

	public function getRequiredInstanceTypes()
	{
		return (array)$this->getField('requiredInstanceTypes');
	}
}