<?php
namespace Yandex\Market\Api\Business\Warehouses;

use Yandex\Market;
use Yandex\Market\Trading\Service\Common\Options;
use Yandex\Market\Psr\Log\LoggerInterface;

class Facade
{
	public static function primaryWarehouse(Options $options, LoggerInterface $logger = null)
	{
		$businessId = static::businessId($options);
		$campaignId = $options->getCampaignId();
		$data = static::warehousesData($businessId, $options, $logger);

		return is_array($data[$campaignId]) ? $data[$campaignId] : [ $data[$campaignId] ];
	}

	public static function storeGroup(Options $options, LoggerInterface $logger = null)
	{
		$businessId = static::businessId($options);
		$campaignId = $options->getCampaignId();
		$data = static::warehousesData($businessId, $options, $logger);

		if (!is_array($data[$campaignId])) { return null; }

		list($primaryWarehouse) = $data[$campaignId];
		$result = [];

		foreach ($data as $campaignId => $row)
		{
			if (is_array($row) && $row[0] === $primaryWarehouse)
			{
				$result[] = $campaignId;
			}
		}

		return $result;
	}

	protected static function warehousesData($businessId, Options $options, LoggerInterface $logger = null)
	{
		$response = (new Request($businessId, $options, $logger))->execute();
		$result = [];

		/** @var Model\Warehouse $warehouse */
		foreach ($response->getWarehouses() as $warehouse)
		{
			$result[$warehouse->getCampaignId()] = $warehouse->getId();
		}

		/** @var Model\WarehouseGroup $warehouseGroup */
		foreach ($response->getWarehouseGroups() as $warehouseGroup)
		{
			/** @var Model\Warehouse $warehouse */
			foreach ($warehouseGroup->getWarehouses() as $warehouse)
			{
				$result[$warehouse->getCampaignId()] = [
					$warehouseGroup->getMainWarehouse()->getId(),
					$warehouseGroup->getMainWarehouse()->getCampaignId(),
				];
			}
		}

		return $result;
	}

	protected static function businessId(Options $options)
	{
		if ($options instanceof Market\Trading\Service\Marketplace\Options)
		{
			return $options->getBusinessId();
		}

		return Market\Api\Campaigns\Facade::businessId($options);
	}
}