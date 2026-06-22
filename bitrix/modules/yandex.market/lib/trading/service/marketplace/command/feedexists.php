<?php

namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

class FeedExists
{
	protected $provider;
	protected $environment;
	protected $rule;

	public function __construct(
		TradingService\Reference\Provider $provider,
		TradingEntity\Reference\Environment $environment,
		FeedExists\Rule $rule = null
	)
	{
		$this->provider = $provider;
		$this->environment = $environment;

		if ($rule !== null)
		{
			$this->rule = $rule;
		}
		else if ($provider instanceof TradingService\Marketplace\Provider)
		{
			$this->rule = new FeedExists\CampaignRule($provider);
		}
	}

	public function filterProducts(array $productIds)
	{
		$catalog = $this->provider->getContext()->getBusiness()->getCatalog();

		if ($catalog !== null && $catalog->wasSubmitted())
		{
			return $this->catalogExists($productIds, $catalog->getId());
		}

		if ($this->rule === null) { return $productIds; }

		$feeds = $this->rule->getFeeds();

		if (empty($feeds)) { return $productIds; }

		return $this->queryExists($feeds, $productIds);
	}

	public function splitProducts(array $productIds)
	{
		$catalog = $this->provider->getContext()->getBusiness()->getCatalog();

		if ($catalog !== null && $catalog->wasSubmitted())
		{
			return $this->catalogSplit($productIds, $catalog->getId());
		}

		if ($this->rule === null) { return [$productIds, []]; }

		$feeds = $this->rule->getFeeds();

		if (empty($feeds))
		{
			return [$productIds, []];
		}

		return $this->splitExists($feeds, $productIds);
	}

	protected function catalogExists(array $productIds, $catalogId)
	{
		$result = [];

		foreach (array_chunk($productIds, 500) as $productChunk)
		{
			$query = Market\Catalog\Run\Storage\OfferTable::getList([
				'filter' => [
					'=CATALOG_ID' => $catalogId,
					'=ELEMENT_ID' => $productChunk,
					'=STATUS' => Market\Catalog\Run\Storage\OfferTable::STATUS_SUCCESS,
				],
				'select' => [ 'ELEMENT_ID' ],
			]);

			while ($row = $query->fetch())
			{
				$result[] = $row['ELEMENT_ID'];
			}
		}

		return $result;
	}

	protected function catalogSplit(array $productIds, $catalogId)
	{
		$exportedMap = [];
		$deletedMap = [];

		foreach (array_chunk($productIds, 500) as $productChunk)
		{
			$query = Market\Catalog\Run\Storage\OfferTable::getList([
				'filter' => [
					'=CATALOG_ID' => $catalogId,
					'=ELEMENT_ID' => $productChunk,
				],
				'select' => [ 'ELEMENT_ID', 'STATUS' ],
			]);

			while ($row = $query->fetch())
			{
				$elementId = $row['ELEMENT_ID'];

				if ($row['STATUS'] === Market\Catalog\Run\Storage\OfferTable::STATUS_SUCCESS)
				{
					$exportedMap[$elementId] = true;
				}
				else
				{
					$deletedMap[$elementId] = true;
				}
			}
		}

		return [array_keys($exportedMap), array_keys($deletedMap)];
	}

	protected function queryExists(array $feeds, array $productIds, $field = 'ELEMENT_ID')
	{
		$result = [];

		foreach (array_chunk($productIds, 500) as $productChunk)
		{
			$query = Market\Export\Run\Storage\OfferTable::getList([
				'filter' => [
					'=SETUP_ID' => $feeds,
					'=' . $field => $productChunk,
					'=STATUS' => Market\Export\Run\Steps\Base::STORAGE_STATUS_SUCCESS,
				],
				'select' => [ $field ],
				'group' => [ $field ],
			]);

			while ($row = $query->fetch())
			{
				$result[] = $row[$field];
			}
		}

		return $result;
	}

	protected function splitExists(array $feeds, array $productIds, $field = 'ELEMENT_ID')
	{
		$exportedMap = [];
		$deletedMap = [];

		foreach (array_chunk($productIds, 500) as $productChunk)
		{
			$query = Market\Export\Run\Storage\OfferTable::getList([
				'filter' => [
					'=SETUP_ID' => $feeds,
					'=' . $field => $productChunk,
				],
				'select' => [ $field, 'STATUS' ],
			]);

			while ($row = $query->fetch())
			{
				$value = $row[$field];

				if ((int)$row['STATUS'] === Market\Export\Run\Steps\Base::STORAGE_STATUS_SUCCESS)
				{
					$exportedMap[$value] = true;
				}
				else if (!isset($exportedMap[$value]))
				{
					$deletedMap[$value] = true;
				}
			}
		}

		if (!empty($deletedMap))
		{
			$deletedMap = array_diff_key($deletedMap, $exportedMap);
		}

		return [array_keys($exportedMap), array_keys($deletedMap)];
	}
}