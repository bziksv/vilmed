<?php

namespace Yandex\Market\Api\Model\Order;

use Yandex\Market;

class Delivery extends Market\Api\Model\Cart\Delivery
{
	/** @deprecated */
	public function getPrice()
	{
		return Market\Data\Number::normalize($this->getField('price'));
	}

	public function getVat()
	{
		return $this->getField('vat');
	}

	public function getShipments()
	{
		return $this->getCollection('shipments', ShipmentCollection::class);
	}

	public function getTracks()
	{
		return $this->getCollection('tracks', TrackCollection::class);
	}

	public function getDates()
	{
		return $this->getModel('dates', Dates::class);
	}

	public function getServiceName()
	{
		return (string)$this->getField('serviceName');
	}

	public function getServiceId()
	{
		return (string)$this->getField('deliveryServiceId');
	}
}