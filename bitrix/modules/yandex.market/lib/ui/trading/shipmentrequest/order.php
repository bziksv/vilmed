<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class Order extends Market\Api\Reference\Model
{
	public function getId()
	{
		return (int)$this->requireField('ID');
	}

	public function getCampaignId()
	{
		return (int)$this->requireField('CAMPAIGN_ID');
	}

	public function getInternalId()
	{
		return (int)$this->requireField('INTERNAL_ID');
	}

	public function getAccountNumber()
	{
		return (string)$this->requireField('ACCOUNT_NUMBER');
	}

	public function getShipmentId()
	{
		return (int)$this->requireField('SHIPMENT_ID');
	}

	public function getInitialBoxCount()
	{
		return (int)$this->requireField('BOX_INITIAL_COUNT');
	}

	public function useAutoFinish()
	{
		return (string)$this->getField('AUTO_FINISH') === 'Y';
	}

	public function getBoxCollection()
	{
		return $this->requireCollection('BOX', BoxCollection::class);
	}

	public function getBasketConfirm()
	{
		return $this->getModel('BASKET_CONFIRM', BasketConfirm::class);
	}
}