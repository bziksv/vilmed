<?php

namespace Yandex\Market\Api\Model;

use Bitrix\Main;
use Yandex\Market;

class Order extends Market\Api\Reference\Model
{
	use Market\Reference\Concerns\HasLang;

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public static function getMeaningfulFields()
	{
		return [
			'EXTERNAL_ID',
		];
	}

	public static function getMeaningfulFieldTitle($fieldName)
	{
		return static::getLang('API_MODEL_ORDER_FIELD_' . $fieldName, null, $fieldName);
	}

	public static function getMeaningfulFieldHelp($fieldName)
	{
		return static::getLang('API_MODEL_ORDER_HELP_' . $fieldName, null, '');
	}

	public function getMeaningfulValues()
	{
		return array_filter([
			'EXTERNAL_ID' => $this->getId(),
		]);
	}

	public function getServiceUrl(Market\Trading\Service\Common\Options $options)
	{
		return 'https://partner.market.yandex.ru/order-info?' . http_build_query([
			'id' => $options->getCampaignId(),
			'orderId' => $this->getId(),
		]);
	}

	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function isFake()
	{
		return (bool)$this->getField('fake');
	}

	public function isCancelRequested()
	{
		return (bool)$this->getField('cancelRequested');
	}

	public function getCreationDate()
	{
		return Market\Data\DateTime::convertFromService($this->getField('creationDate'));
	}

	public function getStatus()
	{
		return (string)$this->requireField('status');
	}

	public function getSubStatus()
	{
		return (string)$this->getField('substatus');
	}

	public function getPaymentType()
	{
		return (string)$this->requireField('paymentType');
	}

	public function getPaymentMethod()
	{
		return $this->getField('paymentMethod');
	}

	public function getCurrency()
	{
		return (string)$this->requireField('currency');
	}

	public function getItemsTotal()
	{
		return Market\Data\Number::normalize($this->getField('itemsTotal'));
	}

	public function getDeliveryTotal()
	{
		return Market\Data\Number::normalize($this->getField('deliveryTotal'));
	}

	/** @deprecated */
	public function getSubsidyTotal()
	{
		return $this->getSubsidies()->getSum();
	}

	/** @deprecated */
	public function getTotal()
	{
		return $this->getItemsTotal() + $this->getDeliveryTotal();
	}

	public function getNotes()
	{
		return (string)$this->getField('notes');
	}

	public function hasDelivery()
	{
		return $this->hasField('delivery');
	}

	/**
	 * @return Order\Delivery
	 */
	public function getDelivery()
	{
		return $this->requireModel('delivery', Order\Delivery::class);
	}

	public function getItems()
	{
		return $this->requireCollection('items', Order\ItemCollection::class);
	}

	public function getSubsidies()
	{
		return $this->getCollection('subsidies', Order\SubsidyCollection::class);
	}
}