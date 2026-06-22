<?php
namespace Yandex\Market\Api\Model\Order;

use Yandex\Market;

class Box extends Market\Api\Reference\Model
{
	public function getFulfilmentId()
	{
		return $this->getField('fulfilmentId');
	}

	/** @deprecated */
	public function getWidth()
	{
		return Market\Data\Number::normalize($this->getField('width'));
	}

	/** @deprecated */
	public function getHeight()
	{
		return Market\Data\Number::normalize($this->getField('height'));
	}

	/** @deprecated */
	public function getDepth()
	{
		return Market\Data\Number::normalize($this->getField('depth'));
	}

	/** @deprecated */
	public function getSizeUnit()
	{
		return Market\Data\Size::UNIT_CENTIMETER;
	}

	/** @deprecated */
	public function getWeight()
	{
		return Market\Data\Number::normalize($this->getField('weight'));
	}

	/** @deprecated */
	public function getWeightUnit()
	{
		return Market\Data\Weight::UNIT_GRAM;
	}

	/** @deprecated */
	public function getItems()
	{
		return $this->getCollection('items', BoxItemCollection::class);
	}
}