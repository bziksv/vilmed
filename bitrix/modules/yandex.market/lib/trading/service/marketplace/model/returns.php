<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model;

use Yandex\Market;

class Returns extends Market\Api\Reference\Model
{
	public function getId()
	{
		return (int)$this->getField('id');
	}

	public function getOrderId()
	{
		return (string)$this->getField('orderId');
	}

	public function getUpdateDate()
	{
		return Market\Data\Date::convertFromService(
			$this->getField('updateDate'),
			'Y-m-d'
		);
	}

	public function getShipmentStatus()
	{
		return (string)$this->getField('shipmentStatus');
	}
}