<?php

namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market;
use Yandex\Market\Trading\Service\Marketplace;

class ProductReserves extends SkeletonReserves
{
	public function execute(array $amounts)
	{
		if (empty($amounts)) { return []; }

		$this->configureEnvironment();

		$productIds = array_column($amounts, 'ID');

		if ($this->provider->getOptions()->getStocksBehavior() === Marketplace\Options::STOCKS_PLAIN)
		{
			$total = $this->loadTotal($productIds);
			$amounts = $this->applyTotal($amounts, $total);

			return $amounts;
		}

		list($processingStates, $otherStates) = $this->loadOrders();
		$allUsedStates = $processingStates + $otherStates;
		$total = $this->loadTotal($productIds);

		$waiting = $this->loadWaiting($processingStates, $productIds);
		$reserves = $this->loadReserves($processingStates, $productIds);
		$siblingReserved = $this->loadSiblingReserves($allUsedStates, array_keys($total));

		$total = $this->decreaseTotal($total, $reserves);
		$total = $this->decreaseTotal($total, $siblingReserved);

		$amounts = $this->applyReserves($amounts, $reserves, true);

		if ($this->environment->getReserve()->filledByStore())
		{
			$amounts = $this->applyReserves($amounts, $siblingReserved, true);
		}

		$amounts = $this->applyTotal($amounts, $total);
		$amounts = $this->applyReserves($amounts, $waiting, true);

		return $amounts;
	}

	protected function applyReserves(array $amounts, array $reserves, $invert = false)
	{
		$sign = ($invert ? -1 : 1);

		foreach ($amounts as &$amount)
		{
			if (!isset($reserves[$amount['ID']])) { continue; }

			$reserve = $reserves[$amount['ID']];

			if (isset($amount['QUANTITY_LIST'][Market\Data\Trading\Stocks::TYPE_FIT]))
			{
				$amount['QUANTITY_LIST'][Market\Data\Trading\Stocks::TYPE_FIT] += $sign * $reserve['QUANTITY'];
			}

			if (isset($amount['QUANTITY']))
			{
				$amount['QUANTITY'] += $sign * $reserve['QUANTITY'];
			}
		}
		unset($amount);

		return $amounts;
	}

	protected function applyTotal(array $amounts, array $total)
	{
		foreach ($amounts as &$amount)
		{
			if (!isset($total[$amount['ID']]['AVAILABLE'])) { continue; }

			$limit = $total[$amount['ID']]['AVAILABLE'];

			if (
				isset($amount['QUANTITY_LIST'][Market\Data\Trading\Stocks::TYPE_FIT])
				&& $amount['QUANTITY_LIST'][Market\Data\Trading\Stocks::TYPE_FIT] > $limit
			)
			{
				$amount['QUANTITY_LIST'][Market\Data\Trading\Stocks::TYPE_FIT] = $limit;
			}

			if (isset($amount['QUANTITY']) && $amount['QUANTITY'] > $limit)
			{
				$amount['QUANTITY'] = $limit;
			}
		}
		unset($amount);

		return $amounts;
	}
}