<?php
namespace Yandex\Market\Trading\Service\Marketplace;

use Yandex\Market\Catalog;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Export\Entity\Trading\EnvironmentMapper;

class CatalogAdapter
{
	private $provider;

	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
	}

	public function getId()
	{
		$catalog = $this->getCatalog();

		return $catalog !== null ? $catalog->getId() : null;
	}

	public function wasSubmitted()
	{
		$catalog = $this->getCatalog();

		return ($catalog !== null && $catalog->wasSubmitted());
	}

	public function isStocksEnabled()
	{
		foreach ($this->stocksSegments() as $segment)
		{
			if ($segment->getParamCollection()->getTagMap()->has('count'))
			{
				return true;
			}
		}

		return false;
	}

	public function getProductStore()
	{
		$partials = [];

		foreach ($this->stocksSegments() as $segment)
		{
			foreach ($segment->getParamCollection()->getTagMap()->get('count') as $tag)
			{
				$partials[] = EnvironmentMapper::parseStores($tag['VALUE']['TYPE'], $tag['VALUE']['FIELD']);
			}
		}

		if (empty($partials)) { return []; }

		return array_merge(...$partials);
 	}

	public function getPackRatioSources()
	{
		$result = [];
		$tagGroups = [
			'count' => $this->stocksSegments(),
			'price' => $this->priceCampaignSegments(),
			'basicPrice' => [ $this->priceBusinessSegment() ],
		];

		foreach ($tagGroups as $tagName => $segments)
		{
			/** @var Catalog\Segment\Model $segment */
			foreach ($segments as $segment)
			{
				if ($segment === null) { continue; }

				foreach ($segment->getParamCollection()->getTagMap()->get($tagName) as $tag)
				{
					if (empty($tag['SETTINGS']['PACK_RATIO']['FIELD'])) { continue; }

					$setting = $tag['SETTINGS']['PACK_RATIO'];
					$key = "{$setting['TYPE']}:{$setting['FIELD']}";

					$result[$key] = [
						$setting['TYPE'],
						$setting['FIELD'],
					];
				}
			}
		}

		return array_values($result);
	}

	public function getStocksBehavior()
	{
		foreach ($this->stocksSegments() as $segment)
		{
			foreach ($segment->getParamCollection()->getTagMap()->get('count') as $tag)
			{
				if (!isset($tag['SETTINGS']['USE_RESERVE'])) { continue; }

				return (string)$tag['SETTINGS']['USE_RESERVE'] === UserField\BooleanType::VALUE_Y
					? Options::STOCKS_ONLY_AVAILABLE
					: Options::STOCKS_PLAIN;
			}
		}

		return Options::STOCKS_PLAIN;
	}

	/** @return Catalog\Segment\Model[] */
	private function stocksSegments()
	{
		$catalog = $this->getCatalog();

		if ($catalog === null || !$catalog->isStockEnabled()) { return []; }

		$campaignMap = array_flip($this->stocksCampaignIds());
		$result = [];

		/** @var Catalog\Product\Model $product */
		foreach ($catalog->getProductCollection() as $product)
		{
			/** @var Catalog\Segment\Model $segment */
			foreach ($product->getStockSegmentCollection() as $segment)
			{
				$campaignId = $segment->getCampaignId();

				if (isset($campaignMap[$campaignId]))
				{
					$result[] = $segment;
					break;
				}
			}
		}

		return $result;
	}

	protected function stocksCampaignIds()
	{
		$context = $this->provider->getContext();
		$campaign = $context->getCampaign();
		$primaryCampaignId = $campaign->getExternalSettings()->getWarehouseGroupPrimary();

		if ($primaryCampaignId === null)
		{
			return [ $campaign->getId() ];
		}

		return $context->getBusiness()->getCampaignCollection()->getWarehouseGroupCampaignIds($primaryCampaignId);
	}

	public function isPriceEnabled()
	{
		foreach ($this->priceCampaignSegments() as $campaignSegment)
		{
			foreach ($campaignSegment->getParamCollection()->getTagMap()->get('price') as $price)
			{
				if (empty($price['VALUE']['FIELD'])) { continue; }

				return true;
			}
		}

		$businessSegment = $this->priceBusinessSegment();

		if ($businessSegment === null) { return null; }

		foreach ($businessSegment->getParamCollection()->getTagMap()->get('basicPrice') as $basicPrice)
		{
			if (empty($basicPrice['VALUE']['FIELD'])) { continue; }

			return true;
		}

		return false;
	}

	/** @return Catalog\Segment\Model[] */
	private function priceCampaignSegments()
	{
		$catalog = $this->getCatalog();

		if ($catalog === null || !$catalog->isPriceEnabled()) { return []; }

		$campaignId = (int)$this->provider->getContext()->getCampaign()->getId();

		if ($campaignId <= 0) { return []; }

		$result = [];

		/** @var Catalog\Product\Model $product */
		foreach ($catalog->getProductCollection() as $product)
		{
			$campaignSegment = $product->getPriceSegmentCollection()->getCampaignItem($campaignId);

			if ($campaignSegment !== null)
			{
				$result[] = $campaignSegment;
			}
		}

		return $result;
	}

	/** @return Catalog\Segment\Model|null */
	private function priceBusinessSegment()
	{
		$catalog = $this->getCatalog();

		if ($catalog === null || !$catalog->isPriceEnabled()) { return null; }

		/** @var Catalog\Product\Model $product */
		foreach ($catalog->getProductCollection() as $product)
		{
			$businessSegment = $product->getPriceSegmentCollection()->getBusinessItem();

			if ($businessSegment !== null)
			{
				return $businessSegment;
			}
		}

		return null;
	}

	/** @return Catalog\Setup\Model|null */
	private function getCatalog()
	{
		return $this->provider->getContext()->getBusiness()->getCatalog();
	}
}