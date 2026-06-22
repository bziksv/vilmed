<?php
namespace Yandex\Market\Trading\Service\Marketplace\Concerns\Action;

use Yandex\Market;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @deprecated
 * trait HasBasketWarehouses
 * @property TradingService\Marketplace\Provider $provider
 * @property TradingEntity\Reference\Environment $environment
 * @property TradingService\Marketplace\Action\Cart\Request|TradingService\Marketplace\Action\OrderAccept\Request $request
 * @method array makeBasketContext()
 * @method array applyQuantitiesRatio($quantities, $packRatio)
 * @method array getProductData($productIds, $quantities, $context)
 * @method array getPriceData($productIds, $quantities, $context)
 * @method array getStoreData($productIds, $quantities, $context)
 * @method array mergeBasketData($dataList)
 */
trait HasBasketWarehouses
{
	protected function getBasketData(Market\Api\Model\Cart\ItemCollection $items, $offerMap = null, $packRatio = null)
	{
		$context = $this->makeBasketContext();
		$productIds = $offerMap !== null ? array_values($offerMap) : $items->getOfferIds();
		$quantities = $items->getQuantities($offerMap);
		$quantities = $this->applyQuantitiesRatio($quantities, $packRatio);

		if (empty($productIds)) { return []; }

		$dataGroups = [
			$this->getProductData($productIds, $quantities, $context),
			$this->getPriceData($productIds, $quantities, $context),
			$this->getStoreData($productIds, $quantities, $context),
		];

		return $this->mergeBasketData($dataGroups);
	}
}