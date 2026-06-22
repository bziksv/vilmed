<?php

namespace Yandex\Market\Trading\Service\Marketplace\Model;

use Yandex\Market;
use Bitrix\Main;

class Order extends Market\Api\Model\Order
{
	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
		parent::includeMessages();
	}

	public static function getMeaningfulFields()
	{
		$result = parent::getMeaningfulFields();
		$result[] = 'DATE_EXPIRY';
		$result[] = 'DATE_SHIPMENT';
		$result[] = 'EAC_CODE';

		return $result;
	}

	public static function getMeaningfulFieldTitle($fieldName)
	{
		$result = static::getLang('TRADING_ACTION_MODEL_ORDER_FIELD_' . $fieldName, null, '');

		if ($result === '')
		{
			$result = parent::getMeaningfulFieldTitle($fieldName);
		}

		return $result;
	}

	public static function getMeaningfulFieldHelp($fieldName)
	{
		$result = static::getLang('TRADING_ACTION_MODEL_ORDER_HELP_' . $fieldName, null, '');

		if ($result === '')
		{
			$result = parent::getMeaningfulFieldHelp($fieldName);
		}

		return $result;
	}

	public function getUpdatedAt()
	{
		return Market\Data\DateTime::convertFromService($this->getField('updatedAt'));
	}

	/** @return Order\Buyer|null */
	public function getBuyer()
	{
		return $this->getModel('buyer', Order\Buyer::class);
	}

	public function getDelivery()
	{
		return $this->requireModel('delivery', Order\Delivery::class);
	}

	public function getItems()
	{
		return $this->requireCollection('items', Order\ItemCollection::class);
	}

	public function getMeaningfulValues()
	{
		$result = parent::getMeaningfulValues();
		$result += array_filter([
			'DATE_EXPIRY' => $this->getExpiryDate(),
			'DATE_SHIPMENT' => $this->getMeaningfulShipmentDates(),
			'EAC_CODE' => $this->getDelivery()->getEacCode(),
		]);

		return $result;
	}

	/**
	 * @return Main\Type\Date[]
	 */
	public function getMeaningfulShipmentDates()
	{
		$result = [];

		if ($this->hasDelivery())
		{
			/** @var Market\Api\Model\Order\Shipment $shipment */
			foreach ($this->getDelivery()->getShipments() as $shipment)
			{
				$date = $shipment->getShipmentDate();

				if ($date !== null)
				{
					$result[] = $date;
				}
			}
		}

		return $result;
	}

	public function getExpiryDate()
	{
		$value = $this->getField('expiryDate');

		return $value !== null ? Market\Data\DateTime::convertFromService($value) : null;
	}
}