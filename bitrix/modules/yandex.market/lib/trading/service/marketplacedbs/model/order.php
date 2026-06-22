<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model;

use Yandex\Market\Trading\Service as TradingService;

class Order extends TradingService\Marketplace\Model\Order
{
	public static function getMeaningfulFields()
	{
		return array_diff(
			parent::getMeaningfulFields(),
			[
				'DATE_EXPIRY',
				'DATE_SHIPMENT',
				'EAC_CODE',
				'VEHICLE_NUMBER',
			]
		);
	}

	public function getPaymentMethod()
	{
		return (string)$this->requireField('paymentMethod');
	}

	public function getDelivery()
	{
		return $this->requireModel('delivery', Order\Delivery::class);
	}

	public function getBuyer()
	{
		return $this->getModel('buyer', Order\Buyer::class);
	}

	public function getMeaningfulValues()
	{
		return array_diff_key(
			parent::getMeaningfulValues(),
			[ 'DATE_SHIPMENT' => true ]
		);
	}
}