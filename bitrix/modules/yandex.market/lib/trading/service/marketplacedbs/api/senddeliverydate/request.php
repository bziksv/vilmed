<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs\Api\SendDeliveryDate;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	protected $orderId;
	protected $date;
	protected $reason;

	public function getPath()
	{
		return sprintf(
			'/v2/campaigns/%s/orders/%s/delivery/date.json',
			$this->getCampaignId(),
			$this->getOrderId()
		);
	}

	public function getQuery()
	{
		return [
			'dates' => [
				'toDate' => Market\Data\Date::convertForService(
					$this->getDate(),
					Market\Data\Date::FORMAT_DEFAULT_SHORT
				),
			],
			'reason' => $this->getReason(),
		];
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_PUT;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function setOrderId($orderId)
	{
		$this->orderId = $orderId;
	}

	public function getOrderId()
	{
		Market\Reference\Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	public function setDate(Main\Type\Date $accepted)
	{
		$this->date = $accepted;
	}

	public function getDate()
	{
		Market\Reference\Assert::notNull($this->date, 'date');

		return $this->date;
	}

	public function setReason($reason)
	{
		$this->reason = $reason;
	}

	public function getReason()
	{
		Market\Reference\Assert::notNull($this->reason, 'reason');

		return (string)$this->reason;
	}
}