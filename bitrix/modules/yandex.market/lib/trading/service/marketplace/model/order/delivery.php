<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model\Order;

use Yandex\Market;

class Delivery extends Market\Api\Model\Order\Delivery
{
	const EAC_TYPE_MERCHANT_TO_COURIER = 'MERCHANT_TO_COURIER';
	const EAC_TYPE_COURIER_TO_MERCHANT = 'COURIER_TO_MERCHANT';

	public function getEacType()
	{
		return $this->getField('eacType');
	}

	public function getEacCode()
	{
		return $this->getField('eacCode');
	}

	public function getCourier()
	{
		return $this->getModel('courier', Delivery\Courier::class);
	}
}