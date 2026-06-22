<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\Returns;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	/** @var Main\Type\Date */
	protected $dateFrom;
	/** @var Main\Type\Date */
	protected $dateTo;
	/** @var int */
	protected $limit;
	/** @var string */
	protected $pageToken;
	/** @var array */
	protected $orderIds;

	public function getPath()
	{
		return sprintf('/campaigns/%s/returns.json', $this->getCampaignId());
	}

	public function getQuery()
	{
		$dateFrom = $this->getDateFrom();
		$dateTo = $this->getDateTo();
		$orderIds = $this->getOrderIds();

		return array_filter([
			'from_date' => $dateFrom !== null ? Market\Data\Date::convertForService($dateFrom, 'Y-m-d') : null,
			'to_date' => $dateTo !== null ? Market\Data\Date::convertForService($dateTo, 'Y-m-d') : null,
			'page_token' => $this->getPageToken(),
			'limit' => $this->getLimit(),
			'orderIds' => is_array($orderIds) ? implode(',', $orderIds) : null,
		]);
	}

	public function processParameters(array $parameters)
	{
		foreach ($parameters as $name => $value)
		{
			switch ($name)
			{
				case 'dateFrom':
					$this->setDateFrom($value);
				break;

				case 'dateTo':
					$this->setDateTo($value);
				break;

				case 'limit':
					$this->setLimit($value);
				break;

				case 'pageToken':
					$this->setPageToken($value);
				break;

				case 'orderIds':
					$this->setOrderIds($value);
				break;
			}
		}
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}

	public function getDateFrom()
	{
		return $this->dateFrom;
	}

	public function setDateFrom(Main\Type\Date $dateFrom = null)
	{
		$this->dateFrom = $dateFrom;
	}

	public function getDateTo()
	{
		return $this->dateTo;
	}

	public function setDateTo(Main\Type\Date $dateTo = null)
	{
		$this->dateTo = $dateTo;
	}

	public function getLimit()
	{
		return $this->limit;
	}

	public function setLimit($limit)
	{
		$this->limit = $limit;
	}

	public function getPageToken()
	{
		return $this->pageToken;
	}

	public function setPageToken($pageToken)
	{
		$this->pageToken = $pageToken;
	}

	public function getOrderIds()
	{
		return $this->orderIds;
	}

	public function setOrderIds($orderIds)
	{
		$this->orderIds = (array)$orderIds;
	}
}
