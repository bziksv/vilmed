<?php

namespace Yandex\Market\Trading\Service\Marketplace\Api\ShipmentAct;

use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;

class Request extends Api\Partner\Reference\Request
{
	protected $shipmentId;

	public function getPath()
	{
		return '/v2/campaigns/' . $this->getCampaignId() . '/first-mile/shipments/' . $this->getShipmentId() . '/act.json';
	}

	/** @return int */
	public function getShipmentId()
	{
		Assert::notNull($this->shipmentId, 'shipmentId');

		return $this->shipmentId;
	}

	public function setShipmentId($shipmentId)
	{
		$this->shipmentId = $shipmentId;
	}

	public function buildResponse($data)
	{
		return new Api\Partner\File\Response();
	}
}
