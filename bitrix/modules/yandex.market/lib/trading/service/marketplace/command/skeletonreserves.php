<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

abstract class SkeletonReserves
{
	protected $provider;
	protected $environment;
	protected $platform;
	protected $orderLoader;

	public function __construct(
		TradingService\Marketplace\Provider $provider,
		TradingEntity\Reference\Environment $environment,
		TradingEntity\Reference\Platform $platform
	)
	{
		$this->provider = $provider;
		$this->environment = $environment;
		$this->platform = $platform;
		$this->orderLoader = new ProcessingOrders($this->provider, $this->environment, $this->platform);
	}

	protected function loadOrders()
	{
		return $this->orderLoader->load();
	}

	protected function configureEnvironment()
	{
		$this->configureEnvironmentPlatform();
		$this->configureEnvironmentReserve();
	}

	protected function configureEnvironmentPlatform()
	{
		$options = $this->provider->getOptions();

		$this->platform = clone $this->platform;
		$this->platform->setSetupId($options->getSetupId());
		$this->platform->setGroupSetupIds($options->getStoreGroup() + [
			$options->getCampaignId() => $options->getSetupId(),
		]);

		$this->orderLoader = new ProcessingOrders($this->provider, $this->environment, $this->platform);
	}

	protected function configureEnvironmentReserve()
	{
		$this->environment->getReserve()->configure([
			'STORES' => $this->provider->getOptions()->getProductStores(),
		]);
	}

	protected function loadWaiting(array $orderStates, array $productIds)
	{
		$orderIds = array_column($orderStates, 'INTERNAL_ID');

		return $this->environment->getReserve()->getWaiting($orderIds, $productIds);
	}

	protected function loadReserves(array $orderStates, array $productIds)
	{
		$orderIds = array_column($orderStates, 'INTERNAL_ID');

		return $this->environment->getReserve()->getReserved($orderIds, $productIds);
	}

	protected function loadSiblingReserves(array $orderStates, array $productIds)
	{
		$orderIds = array_map(static function($orderState) { return $orderState['INTERNAL_ID']; }, $orderStates);

		return $this->environment->getReserve()->getSiblingReserved(
			$orderIds,
			$productIds,
			$this->orderLoader->expireDate()
		);
	}

	protected function loadTotal(array $productIds)
	{
		return $this->environment->getStore()->getTotal(
			$this->provider->getOptions()->getProductStores(),
			$productIds
		);
	}

	protected function decreaseTotal(array $total, array $reserves)
	{
		foreach ($reserves as $productId => $reserve)
		{
			if (!isset($total[$productId]['TOTAL'])) { continue; }

			$totalRow = &$total[$productId];
			$totalRow['TOTAL'] -= $reserve['QUANTITY'];

			if ($totalRow['TOTAL'] < $totalRow['AVAILABLE'])
			{
				$totalRow['AVAILABLE'] = $totalRow['TOTAL'];
			}

			unset($totalRow);
		}

		return $total;
	}
}