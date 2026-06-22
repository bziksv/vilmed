<?php
namespace Yandex\Market\Api\Business\Warehouses\Model;

use Yandex\Market;

class WarehouseGroup extends Market\Api\Reference\Model
{
	public function getName()
	{
		return (string)$this->requireField('name');
	}

	/** @return Warehouse */
	public function getMainWarehouse()
	{
		return $this->requireModel('mainWarehouse', Warehouse::class);
	}

	/** @return WarehouseCollection */
	public function getWarehouses()
	{
		return $this->requireCollection('warehouses', WarehouseCollection::class);
	}
}