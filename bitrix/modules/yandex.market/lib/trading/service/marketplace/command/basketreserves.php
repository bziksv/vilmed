<?php

namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market\Trading\Service\Marketplace;

class BasketReserves extends SkeletonReserves
{
	protected $debug = [];

	public function execute(array $storeData)
	{
		$this->resetDebug();

		$quantities = $this->mapQuantities($storeData);
		$quantities = array_filter($quantities, static function($value) { return $value > 0; });

		if (empty($quantities)) { return $storeData; }

		$this->configureEnvironment();

		$productIds = array_keys($quantities);

		if ($this->provider->getOptions()->getStocksBehavior() === Marketplace\Options::STOCKS_PLAIN)
		{
			$total = $this->loadTotal($productIds);
			$storeData = $this->applyTotal($storeData, $total);

			return $storeData;
		}

		list($processingStates, $otherStates) = $this->loadOrders();
		$allUsedStates = $processingStates + $otherStates;
		$total = $this->loadTotal($productIds);

		$waiting = $this->loadWaiting($processingStates, $productIds);
		$reserves = $this->loadReserves($processingStates, $productIds);
		$siblingReserves = $this->loadSiblingReserves($allUsedStates, array_keys($total));

		$this->storeDebug('MARKET', $reserves);
		$this->storeDebug('SIBLING', $siblingReserves);
		$this->storeDebug('WAITING', $waiting);

		$total = $this->decreaseTotal($total, $reserves);
		$total = $this->decreaseTotal($total, $siblingReserves);

		$storeData = $this->applyReserves($storeData, $reserves);

		if ($this->environment->getReserve()->filledByStore())
		{
			$storeData = $this->applyReserves($storeData, $siblingReserves);
		}

		$storeData = $this->applyTotal($storeData, $total);
		$storeData = $this->applyReserves($storeData, $waiting);

		return $storeData;
	}

	protected function mapQuantities(array $storeData)
	{
		$result = [];

		foreach ($storeData as $productId => $productValues)
		{
			if (!isset($productValues['AVAILABLE_QUANTITY'])) { continue; }

			$result[$productId] = $productValues['AVAILABLE_QUANTITY'];
		}

		return $result;
	}

	protected function applyReserves(array $storeData, array $reserves)
	{
		foreach ($storeData as $productId => &$productValues)
		{
			if (!isset($reserves[$productId])) { continue; }

			$productValues['AVAILABLE_QUANTITY'] -= max(0, $reserves[$productId]['QUANTITY']);
		}
		unset($productValues);

		return $storeData;
	}

	protected function applyTotal(array $storeData, array $total)
	{
		foreach ($storeData as $productId => &$productValues)
		{
			if (!isset($total[$productId]['AVAILABLE'])) { continue; }

			$limit = $total[$productId]['AVAILABLE'];

			if ($productValues['AVAILABLE_QUANTITY'] > $limit)
			{
				$productValues['AVAILABLE_QUANTITY'] = $limit;
			}
		}
		unset($productValues);

		return $storeData;
	}

	protected function resetDebug()
	{
		$this->debug = [];
	}

	protected function storeDebug($key, array $reserves)
	{
		$this->debug[$key] = $reserves;
	}

	public function findDebugProduct($productId)
	{
		$result = [];

		foreach ($this->debug as $key => $reserves)
		{
			if (!isset($reserves[$productId])) { continue; }

			$result[$key] = $reserves[$productId];
		}

		return $result;
	}
}