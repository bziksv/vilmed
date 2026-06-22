<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class DigitalItem extends Market\Api\Reference\Model
{
	public function getCode()
	{
		return (string)$this->getField('CODE');
	}

	public function getActivateTill()
	{
		return $this->requireField('ACTIVATE_TILL');
	}
}