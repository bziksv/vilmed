<?php
namespace Yandex\Market\Trading\Service\Common\Command;

use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Export;

class SkuMap
{
	protected $provider;
	protected $environment;
	protected $useDuplicates;

	public function __construct(
		TradingService\Reference\Provider $provider,
		TradingEntity\Reference\Environment $environment,
		$useDuplicates = true
	)
	{
		$this->provider = $provider;
		$this->environment = $environment;
		$this->useDuplicates = $useDuplicates;
	}

	public function make(array $productIds)
	{
		$options = $this->provider->getOptions();
		$optionMap = $options->getProductSkuMap();
		$optionPrefix = $options->getProductSkuPrefix();
		$result = null;

		if (!empty($optionMap))
		{
			$result = $this->environment->getProduct()->getSkuMap($productIds, $optionMap);
		}

		if ($optionPrefix !== '')
		{
			if ($result === null)
			{
				$result = array_combine($productIds, $productIds);
			}

			$result = array_map(static function($sku) use ($optionPrefix) {
				return $optionPrefix . $sku;
			}, $result);
		}

		return $this->resolveFeedDuplicates($result);
	}

	protected function resolveFeedDuplicates(array $skuMap = null)
	{
		if (!$this->useDuplicates || empty($skuMap)) { return $skuMap; }
		if (!($this->provider instanceof TradingService\Marketplace\Provider)) { return $skuMap; }

		$catalogAdapter = $this->provider->getCatalogAdapter();

		if ($catalogAdapter->wasSubmitted())
		{
			$catalogPrimary = new TradingService\Marketplace\Command\CatalogPrimary();
			$exported = $catalogPrimary->exported(array_values($skuMap), $catalogAdapter->getId());

			list($newMap, $deleted) = $this->unsetSkuWithOtherElement($skuMap, $exported);

			if (empty($deleted)) { return $skuMap; }
		}
		else
		{
			$feedPrimary = new FeedPrimary();
			$feeds = $this->provider->getOptions()->getProductFeeds();
			$exported = $feedPrimary->exported(array_values($skuMap), $feeds);

			list($newMap, $deleted) = $this->unsetSkuWithOtherElement($skuMap, $exported);

			if (empty($deleted) || !$feedPrimary->canUsePrimaryAsSku($feeds)) { return $skuMap; }
		}

		return $newMap;
	}

	protected function unsetSkuWithOtherElement(array $skuMap, array $exported)
	{
		$deleted = [];

		foreach ($skuMap as $productId => $sku)
		{
			if (!isset($exported[$sku])) { continue; }

			if (!in_array($productId, $exported[$sku], true))
			{
				$deleted[] = $sku;
				unset($skuMap[$productId]);
			}
		}

		return [$skuMap, $deleted];
	}
}