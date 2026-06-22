<?php
namespace Yandex\Market\Api\Business\Warehouses;

use Yandex\Market;

class Response extends Market\Api\Reference\ResponseWithResult
{
	public function getWarehouses()
	{
		return $this->getCollection('result.warehouses', Model\WarehouseCollection::class);
	}

	public function getWarehouseGroups()
	{
		return $this->getCollection('result.warehouseGroups', Model\WarehouseGroupCollection::class);
	}
}