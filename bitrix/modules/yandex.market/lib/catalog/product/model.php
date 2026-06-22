<?php
namespace Yandex\Market\Catalog\Product;

use Yandex\Market\Catalog;
use Yandex\Market\Export;
use Yandex\Market\Watcher;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Reference\Concerns;

class Model extends Storage\Model
{
	use Concerns\HasOnce;

	public static function getDataClass()
	{
		return Table::class;
	}

	public function getParent()
	{
		/** @var Catalog\Setup\Model $parent */
		$parent = parent::getParent();

		Assert::isInstanceOf($parent, Catalog\Setup\Model::class);

		return $parent;
	}

	/** @return Catalog\Endpoint\EndpointCollection */
	public function getEndpointCollection()
	{
		return $this->once('getEndpoints', function() {
			$endpoints = [];
			$business = $this->getParent()->getBusiness();

			foreach ($this->getActiveSegments() as $type => $segmentCollection)
			{
				$factory = Catalog\Segment\Registry::factory($type);

				foreach ($factory->endpoints($business, $segmentCollection) as $endpoint)
				{
					$endpoints[] = $endpoint;
				}
			}

			return new Catalog\Endpoint\EndpointCollection($endpoints);
		});
	}

	/** @return array<string, Catalog\Segment\Collection> */
	public function getActiveSegments()
	{
		$parent = $this->getParent();

		return array_filter([
			Catalog\Glossary::SEGMENT_PRICE => $parent->isPriceEnabled() ? $this->getPriceSegmentCollection() : null,
			Catalog\Glossary::SEGMENT_STOCKS => $parent->isStockEnabled() ? $this->getStockSegmentCollection() : null,
			Catalog\Glossary::SEGMENT_OFFER => $parent->isOfferEnabled() ? $this->getOfferSegmentCollection() : null,
			Catalog\Glossary::SEGMENT_CARD => $parent->isCardEnabled() ? $this->getCardSegmentCollection() : null,
		]);
	}

	public function getOfferSegmentCollection()
	{
		return $this->getCollection('OFFER_SEGMENT', Catalog\Segment\Collection::class);
	}

	public function getPriceSegmentCollection()
	{
		return $this->getCollection('PRICE_SEGMENT', Catalog\Segment\Collection::class);
	}

	public function getStockSegmentCollection()
	{
		return $this->getCollection('STOCK_SEGMENT', Catalog\Segment\Collection::class);
	}

	public function getCardSegmentCollection()
	{
		return $this->getCollection('CARD_SEGMENT', Catalog\Card\Collection::class);
	}

	public function isExportAll()
	{
		return (string)$this->getField('EXPORT_ALL') === Storage\Table::BOOLEAN_Y;
	}

	public function getFilterCollection()
	{
		return $this->once('getFilterCollection', function() {
			$collection = $this->getCollection('FILTER', Export\Filter\Collection::class);

			if ($this->isExportAll())
			{
				$allModel = Export\Filter\Model::makeForAll();

				$collection->addItem($allModel);
				$allModel->setParentCollection($collection);
			}

			return $collection;
		});
	}

	public function getIblockId()
	{
		return (int)$this->getField('IBLOCK_ID');
	}

	public function getContext()
	{
		return $this->getIblockContext() + $this->getEndpointContext();
	}

	private function getIblockContext()
	{
		return Export\Entity\Iblock\Provider::getContext($this->getIblockId());
	}

	private function getEndpointContext()
	{
		$result = [
			'CAMPAIGN_GROUP' => [],
		];

		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($this->getEndpointCollection() as $endpoint)
		{
			$driver = $endpoint->getDriver();

			if (!($driver instanceof Catalog\Endpoint\DriverWithCampaignGroup)) { continue; }

			$result['CAMPAIGN_GROUP'][$driver->campaignId()] = $driver->campaignGroup();
		}

		return $result;
	}

    public function getTrackSourceList()
    {
        $sourceList = $this->getUsedSources();
        $context = $this->getContext();
        $result = [];

        foreach ($sourceList as $sourceType)
        {
            $eventHandler = Export\Entity\Manager::getEvent($sourceType);

            $result[] = [
                'SOURCE_TYPE' => $sourceType,
                'SOURCE_PARAMS' => $eventHandler->getSourceParams($context),
            ];
        }

        return $result;
    }

    private function getUsedSources()
    {
        $select = [];
        $filter = [];

        foreach ($this->getActiveSegments() as $segmentCollection)
        {
            /** @var Catalog\Segment\Model $segment */
            foreach ($segmentCollection as $segment)
            {
                $select = $segment->getParamCollection()->getTagMap()->getSourceSelect($select);
            }
        }

        /** @var Export\Filter\Model $segment */
        foreach ($this->getFilterCollection() as $filterModel)
        {
            $filter += array_flip($filterModel->getUsedSources());
        }

        return array_keys($select + $filter);
    }

    public function getSetupBindEntities()
    {
        $context = $this->getContext();
        $result = [
            new Watcher\Track\BindEntity(Catalog\Glossary::ENTITY_OFFER, $context['IBLOCK_ID']),
        ];

        if ($context['HAS_OFFER'])
        {
            $result[] = new Watcher\Track\BindEntity(Catalog\Glossary::ENTITY_OFFER, $context['OFFER_IBLOCK_ID']);
        }

        if ($this->hasCurrencyConversion())
        {
            $result[] = new Watcher\Track\BindEntity(Catalog\Glossary::ENTITY_CURRENCY);
        }

        return $result;
    }

    private function hasCurrencyConversion()
    {
        if (!$this->getParent()->isPriceEnabled()) { return false; }

        /** @var Catalog\Segment\Model $segment */
        foreach ($this->getPriceSegmentCollection() as $segment)
        {
            $tagMap = $segment->getParamCollection()->getTagMap();

            foreach ([ 'price', 'basicPrice', 'discountBase', 'currencyId' ] as $tagName)
            {
                foreach ($tagMap->get($tagName) as $tagDescription)
                {
                    if (!isset($tagDescription['VALUE']['TYPE'], $tagDescription['VALUE']['FIELD'])) { continue; }

                    $source = Export\Entity\Manager::getSource($tagDescription['VALUE']['TYPE']);

                    if (
                        method_exists($source, 'hasCurrencyConversion')
                        && $source->hasCurrencyConversion($tagDescription['VALUE']['FIELD'], $tagDescription['SETTINGS'])
                    )
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}