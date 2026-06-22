<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\PushStocks;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @property TradingService\Marketplace\Provider $provider
 * @property Request $request
*/
class Action extends TradingService\Reference\Action\DataAction
{
	use Market\Reference\Concerns\HasMessage;

	protected $pushStore;

	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	public function process()
	{
		$productIds = $this->getProducts();

		if (empty($productIds))
		{
			$this->finalize();
			return;
		}

		$this->collectNext($productIds);

		$pushStore = $this->getPushStore();
		list($exportedIds, $deletedIds) = $this->feedSplit($productIds);
		$amounts = $this->getAmounts($exportedIds);
		$amounts = $this->applyReserves($amounts);
		$amounts = $this->applyPackRatio($amounts);
		$amounts = $this->applyMissing($amounts, $exportedIds);
		$amounts = $this->applyDeleted($amounts, $deletedIds);
		$amounts = $this->applyAmountsSku($amounts);
		$amounts = $this->extendPushed($amounts, $productIds);
		$amounts = $this->filterValid($amounts);
		$chunkSize = $this->getSkusChunkSize();

		if (!$this->request->isForce())
		{
			list($amounts, $unchanged) = $pushStore->splitChanged($amounts);

			$pushStore->touch($unchanged);
		}

		foreach (array_chunk($amounts, $chunkSize) as $amountsChunk)
		{
			$skus = $this->buildSkus($amountsChunk);

			$this->sendSkus($skus);
			$pushStore->commit($amountsChunk);
		}

		if (!$this->response->getField('hasNext'))
		{
			$this->finalize();
		}
	}

	protected function finalize()
	{
		$action = $this->request->getAction();
		$timestamp = $this->request->getTimestamp();

		if ($action !== Market\Trading\State\PushAgent::ACTION_REFRESH || $timestamp === null) { return; }

		$pushStore = $this->getPushStore();
		$untouched = $pushStore->untouched($timestamp, [ '>VALUE' => 0 ]);
		$chunkSize = $this->getSkusChunkSize();

		foreach (array_chunk($untouched, $chunkSize) as $amountsChunk)
		{
			$skus = $this->emulateSkus($amountsChunk);

			$this->sendSkus($skus);
			$pushStore->release($amountsChunk);
		}
	}

	protected function getProducts()
	{
		$stores = $this->provider->getOptions()->getProductStores();
		$timestamp = null;

		if ($this->request->getAction() !== Market\Trading\State\PushAgent::ACTION_REFRESH)
		{
			$timestamp = $this->request->getTimestamp();
		}

		return $this->environment->getStore()->getChanged(
			$stores,
			$timestamp,
			$this->request->getOffset(),
			$this->request->getLimit()
		);
	}

	protected function feedSplit($productIds)
	{
		$command = new TradingService\Marketplace\Command\FeedExists(
			$this->provider,
			$this->environment,
            new TradingService\Marketplace\Command\FeedExists\GroupRule($this->provider)
		);

		return $command->splitProducts($productIds);
	}

	protected function collectNext($productIds)
	{
		$offset = $this->request->getOffset();
		$limit = $this->request->getLimit();
		$found = count($productIds);

		if ($found < $limit) { return; }

		$this->response->setField('hasNext', true);
		$this->response->setField('offset', $offset + $limit);
	}

	protected function getAmounts($productIds)
	{
		$stores = $this->provider->getOptions()->getProductStores();

		return $this->environment->getStore()->getAmounts($stores, $productIds);
	}

	protected function applyAmountsSku($amounts, array $used = [])
	{
		$productIds = array_column($amounts, 'ID');
		$skuMap = $this->getSkuMap($productIds);

		if ($skuMap === null) { return $amounts; }

		$result = [];

		foreach ($amounts as $amount)
		{
			if (!isset($skuMap[$amount['ID']])) { continue; }

			$sku = trim($skuMap[$amount['ID']]);

			if (isset($used[$sku])) { continue; }

			$amount['~ID'] = $amount['ID'];
			$amount['ID'] = $sku;

			$result[] = $amount;
			$used[$sku] = true;
		}

		return $result;
	}

	protected function getSkuMap($productIds)
	{
		$command = new TradingService\Common\Command\SkuMap(
			$this->provider,
			$this->environment
		);

		return $command->make($productIds);
	}

	protected function applyMissing($amounts, $productIds)
	{
		$amountsExists = array_column($amounts, 'ID', 'ID');
		$missingMap = array_diff_key(array_flip($productIds), $amountsExists);

		foreach ($missingMap as $productId => $dummy)
		{
			$amounts[] = [
				'ID' => $productId,
				'QUANTITY' => 0,
			];
		}

		return $amounts;
	}

	protected function applyDeleted($amounts, $productIds)
	{
		foreach ($productIds as $productId)
		{
			$amounts[] = [
				'ID' => $productId,
				'QUANTITY' => 0,
			];
		}

		return $amounts;
	}

	protected function applyReserves($amounts)
	{
		$command = new TradingService\Marketplace\Command\ProductReserves(
			$this->provider,
			$this->environment,
			$this->getPlatform()
		);

		return $command->execute($amounts);
	}

	protected function applyPackRatio($amounts)
	{
		$command = new TradingService\Marketplace\Command\AmountsPackRatio(
			$this->provider,
			$this->environment
		);

		return $command->execute($amounts);
	}

	protected function extendPushed($amounts, $productIds)
	{
		$existsIds = array_map(static function($amount) { return isset($amount['~ID']) ? $amount['~ID'] : $amount['ID']; }, $amounts);
		$missingMap = array_diff_key(array_flip($productIds), array_flip($existsIds));
		$missingIds = array_keys($missingMap);

		if (empty($missingIds)) { return $amounts; }

		$existsSkus = array_fill_keys(array_column($amounts, 'ID'), true);

		$missing = $this->applyDeleted([], $missingIds);
		$missing = $this->applyAmountsSku($missing, $existsSkus);
		$missing = $this->getPushStore()->filterExists($missing, [ '>VALUE' => 0 ]);

		if (empty($missing)) { return $amounts; }

		array_push($amounts, ...$missing);

		return $amounts;
	}

	protected function filterValid($amounts)
	{
		foreach ($amounts as $key => $amount)
		{
			if (!isset($amount['ID']) || !Market\Data\TextString::match('#^[ \w.,\\\\/()[\]=_-]{1,255}$#u', $amount['ID']))
			{
				$this->provider->getLogger()->warning(self::getMessage('SKU_INVALID', [
					'#ID#' => $amount['ID'],
				]));

				unset($amounts[$key]);
			}
		}

		return $amounts;
	}

	protected function buildSkus($amounts)
	{
		$result = [];
		$updatedAt = Market\Data\Date::convertForService(new Main\Type\DateTime());

		foreach ($amounts as $amount)
		{
			$item = [
				'sku' => (string)$amount['ID'],
				'items' => [],
			];

			if (isset($amount['QUANTITY_LIST']))
			{
				foreach ($amount['QUANTITY_LIST'] as $type => $quantity)
				{
                    if ($type !== Market\Data\Trading\Stocks::TYPE_FIT) { continue; }

					$item['items'][] = [
						'count' => $this->normalizeItemCount($quantity),
						'updatedAt' => $updatedAt,
					];
				}
			}
			else if (isset($amount['QUANTITY']))
			{
				$item['items'][] = [
					'count' => $this->normalizeItemCount($amount['QUANTITY']),
					'updatedAt' => $updatedAt,
				];
			}

			$result[] = $item;
		}

		return $result;
	}

	protected function emulateSkus($amounts)
	{
		$result = [];
		$updatedAt = new Main\Type\DateTime();
		$updatedAt = Market\Data\Date::convertForService($updatedAt);

		foreach ($amounts as $amount)
		{
			$result[] = [
				'sku' => (string)$amount['ID'],
				'items' => [
					[
						'count' => 0,
						'updatedAt' => $updatedAt,
					],
				],
			];
		}

		return $result;
	}

	protected function normalizeItemCount($count)
	{
		return max(0, (int)$count);
	}

	protected function getSkusChunkSize()
	{
		return (int)Market\Config::getOption('push_stocks_chunk', 2000);
	}

	protected function sendSkus($skus)
	{
		if (empty($skus)) { return; }

		$campaign = $this->provider->getContext()->getCampaign();
		$campaignId = $campaign->getExternalSettings()->getWarehouseGroupPrimary() ?: $campaign->getId();

		$request = $this->provider->getRequestFactory()->create(TradingService\Marketplace\Api\SendStocks\Request::class);
		$request->setCampaignId($campaignId);
		$request->setSkus($skus);

		$request->execute();
	}

	protected function getPushStore()
	{
		if ($this->pushStore === null)
		{
			$this->pushStore = $this->creatPushStore();
		}

		return $this->pushStore;
	}

	protected function creatPushStore()
	{
		$setupId =
			$this->provider->getOptions()->getStoreGroupPrimarySetup()
			?: $this->provider->getOptions()->getSetupId();

		return new Market\Trading\State\PushStore(
			$setupId,
			Market\Trading\Entity\Registry::ENTITY_TYPE_STOCKS,
			['ID'],
			[$this, 'pushStoreSign']
		);
	}

	public function pushStoreSign($amount)
	{
		if (isset($amount['QUANTITY_LIST']))
		{
			$parts = [];

			foreach ($amount['QUANTITY_LIST'] as $type => $quantity)
			{
				$parts[] = $type . '=' . (int)$quantity;
			}

			$result = implode(':', $parts);
		}
		else if (isset($amount['QUANTITY']))
		{
			$result = (int)$amount['QUANTITY'];
		}
		else
		{
			$result = null;
		}

		return $result;
	}
}