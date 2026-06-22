<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class BasketConfirm extends Market\Api\Reference\Model
{
	public function isAllowRemove()
	{
		return ($this->getField('ALLOW_REMOVE') === 'Y');
	}

	public function getReason()
	{
		return $this->getField('REASON');
	}
}