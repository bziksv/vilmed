<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

class ProcessingOrders
{
	const ORDER_PROCESSING = 'processing';
	const ORDER_CANCELLED = 'cancelled';
	const ORDER_SHIPPED = 'shipped';

	private $provider;
	private $environment;
	private $platform;

	public function __construct(
		TradingService\Marketplace\Provider $provider,
		TradingEntity\Reference\Environment $environment,
		TradingEntity\Reference\Platform $platform
	)
	{
		$this->provider = $provider;
		$this->environment = $environment;
		$this->platform = $platform;
	}

	public function load()
	{
		$orderStates = $this->processingOrders();
		$orderStates = $this->filterFakeOrders($orderStates);
		$orderStates = $this->mapExistsOrders($orderStates);

		return $this->splitProcessingOrders($orderStates);
	}

	private function processingOrders()
	{
		$result = [];
		$statusService = $this->provider->getStatus();
		$filter = [
			'=SERVICE' => $this->provider->getUniqueKey(),
			'>TIMESTAMP_X' => $this->expireDate(),
		];

		$query = Market\Trading\State\Internals\StatusTable::getList([
			'filter' => $filter,
			'select' => [
				'ENTITY_ID',
				'VALUE',
				'TIMESTAMP_X',
			],
		]);

		while ($row = $query->fetch())
		{
			list($storedStatus, $storedSubstatus) = explode(':', $row['VALUE'], 2);

			if ($statusService->isShipped($storedStatus, $storedSubstatus))
			{
				$status = static::ORDER_SHIPPED;
			}
			else if ($statusService->isCanceled($storedStatus, $storedSubstatus))
			{
				$status = static::ORDER_CANCELLED;
			}
			else
			{
				$status = static::ORDER_PROCESSING;
			}

			$result[$row['ENTITY_ID']] = [
				'STATE' => $status,
				'TIMESTAMP_X' => $row['TIMESTAMP_X'],
			];
		}

		return $result;
	}

	public function expireDate()
	{
		$days = (int)Market\Config::getOption('trading_reserve_days', 3);
		$days = max(1, $days);

		$result = new Main\Type\DateTime();
		$result->add(sprintf('-P%sD', $days));
		$result->setTime(0, 0);

		return $result;
	}

	private function filterFakeOrders(array $orderStates)
	{
		if (empty($orderStates) || Market\Config::isDevMode()) { return $orderStates; }

		$queryFakes = Market\Trading\State\Internals\DataTable::getList([
			'filter' => [
				'=SERVICE' => $this->provider->getUniqueKey(),
				'=ENTITY_ID' => array_keys($orderStates),
				'=NAME' => 'FAKE',
				'=VALUE' => 'Y',
			],
			'select' => [ 'ENTITY_ID' ],
		]);

		$fakes = $queryFakes->fetchAll();
		$fakeIds = array_column($fakes, 'ENTITY_ID');

		return array_diff_key($orderStates, array_flip($fakeIds));
	}

	private function mapExistsOrders(array $orderStates)
	{
		$orderRegistry = $this->environment->getOrderRegistry();
		$orderMap = $orderRegistry->searchList(array_keys($orderStates), $this->platform, false);
		$orderStates = array_intersect_key($orderStates, $orderMap);

		foreach ($orderMap as $externalId => $internalId)
		{
			$orderStates[$externalId]['INTERNAL_ID'] = $internalId;
		}

		return $orderStates;
	}

	private function splitProcessingOrders(array $orderStates)
	{
		$processing = [];
		$other = [];

		foreach ($orderStates as $externalId => $orderState)
		{
			if ($orderState['STATE'] === static::ORDER_PROCESSING)
			{
				$processing[$externalId] = $orderState;
			}
			else
			{
				$other[$externalId] = $orderState;
			}
		}

		return [$processing, $other];
	}
}