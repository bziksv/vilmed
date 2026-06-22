<?php
namespace Yandex\Market\Trading\Campaign;

use Yandex\Market\Api;

class ExternalSettings
{
	private $values;

	public static function fromApi(Api\Business\Warehouses\Model\WarehouseGroup $group = null)
	{
		$values = [];

		if ($group !== null)
		{
			$values['WAREHOUSE_GROUP_NAME'] = $group->getName();
			$values['WAREHOUSE_GROUP_PRIMARY'] = $group->getMainWarehouse()->getCampaignId();
		}

		return new static($values);
	}

	public function __construct($values = null)
	{
		$this->setValues($values);
	}

	public function getWarehouseGroupPrimary()
	{
		if (!isset($this->values['WAREHOUSE_GROUP_PRIMARY']))
		{
			return null;
		}

		return (int)$this->values['WAREHOUSE_GROUP_PRIMARY'];
	}
	public function getWarehouseGroupName()
	{
		if (!isset($this->values['WAREHOUSE_GROUP_NAME']))
		{
			return null;
		}

		return $this->values['WAREHOUSE_GROUP_NAME'];
	}

	public function setValues($values)
	{
		$this->values = is_array($values) ? $values : [];
	}

	public function extendValues($values)
	{
		if (is_array($values))
		{
			$this->values = $values + $this->values;
		}

		return $this;
	}

	public function getValues()
	{
		return $this->values;
	}
}