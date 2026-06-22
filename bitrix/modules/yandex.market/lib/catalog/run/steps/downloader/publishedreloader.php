<?php
namespace Yandex\Market\Catalog\Run\Steps\Downloader;

use Yandex\Market\Api\Business\OfferMappings;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data;
use Yandex\Market\Catalog;

class PublishedReloader
{
    protected $processor;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
    }

    public function run(Data\Run\Offset $offset)
    {
        /** @var OfferMappings\OfferMappingCollection $offerMappings */
        foreach ($this->offerMappings($offset) as $offerMappings)
        {
            $offers = $this->offers($offerMappings->getOfferIds());
            $skuMap = array_column($offers, 'ELEMENT_ID', 'SKU');
            $offerCampaigns = $this->offerCampaigns($offers);
            list($assortment, $placement, $endpoints) = $this->compile($offerMappings, $skuMap, $offerCampaigns);

            $this->writeAssortment($assortment);
            $this->writePlacement($placement);
            $this->writeEndpoints($this->compileEndpoints($endpoints));
        }
    }

    private function offerMappings(Data\Run\Offset $offset)
    {
	    $business = $this->processor->getSetup()->getBusiness();

        do
        {
            $page = $offset->get('page');

            $request = new OfferMappings\Request($business->getId(), $business->getOptions()->getApiAuth(), $business->createLogger());
            $request->setLimit(OfferMappings\Request::OFFERS_LIMIT);

            if ($page !== null) { $request->setPageToken($page); }

            $response = $request->execute();
            $offerMappings = $response->getOfferMappings();

            if ($offerMappings->count() > 0)
            {
                yield $response->getOfferMappings();
            }

            if (!$response->getPaging()->hasNext()) { break; }

            $offset->set('page', $response->getPaging()->getNextPageToken());

            if ($this->processor->isExpired())
            {
                $offset->interrupt();
                break;
            }
        }
        while (true);
    }

    private function offers(array $skus)
    {
        if (empty($skus)) { return []; }

        $result = [];

        $query = Storage\OfferTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=SKU' => $skus,
            ],
            'select' => [ 'SKU', 'ELEMENT_ID', 'STATUS' ],
        ]);

        while ($row = $query->fetch())
        {
            $result[$row['SKU']] = $row;
        }

        return $result;
    }

    private function offerCampaigns(array $offers)
    {
        if (empty($offers)) { return []; }

        $offerStatuses = array_column($offers, 'STATUS', 'ELEMENT_ID');
        $result = array_map(static function($status) {
            return [ 0 => ($status === Storage\OfferTable::STATUS_SUCCESS) ];
        }, $offerStatuses);

        $query = Storage\HashTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=ELEMENT_ID' => array_keys($result),
                '>CAMPAIGN_ID' => 0,
            ],
            'select' => [ 'ELEMENT_ID', 'CAMPAIGN_ID', 'STATUS' ],
        ]);

        while ($row = $query->fetch())
        {
            $elementId = (int)$row['ELEMENT_ID'];
            $campaignId = (int)$row['CAMPAIGN_ID'];
            $valid = ($row['STATUS'] === Storage\HashTable::STATUS_SUCCESS);

            if (!$valid || !isset($result[$elementId][$campaignId]))
            {
                $result[$elementId][$campaignId] = $valid;
            }
        }

        return $result;
    }

    private function compile(OfferMappings\OfferMappingCollection $offerMappings, array $skuMap, array $offerCampaigns)
    {
        $assortment = [];
        $placement = [];
        $endpoints = [];
        $catalogId = $this->processor->getSetup()->getId();
        $now = new Data\Type\CanonicalDateTime();

        /** @var OfferMappings\OfferMapping $offerMapping */
        foreach ($offerMappings as $offerMapping)
        {
            $offer = $offerMapping->getOffer();
            $sku = $offer->getOfferId();
            $categoryId = $offerMapping->getMapping()->getMarketCategoryId();

            if (!isset($skuMap[$sku]))
            {
                if ($offer->isArchived(0)) { continue; }

                $endpoints[] = [
                    'CATALOG_ID' => $catalogId,
                    'SKU' => $sku,
                    'ENDPOINT' => Catalog\Glossary::ENDPOINT_ARCHIVE,
                    'CAMPAIGN_ID' => 0,
                    'PAYLOAD' => [ 'value' => true ],
                ];
                continue;
            }

            $elementId = $skuMap[$sku];

            foreach ($offerCampaigns[$elementId] as $campaignId => $valid)
            {
                $archived = $offer->isArchived($campaignId);

                if ($archived !== !$valid)
                {
                    $endpoints[] = [
                        'CATALOG_ID' => $catalogId,
                        'SKU' => $sku,
                        'ENDPOINT' => Catalog\Glossary::ENDPOINT_ARCHIVE,
                        'CAMPAIGN_ID' => $campaignId,
                        'PAYLOAD' => [ 'value' => !$valid ],
                    ];
                }

                $placement[] = [
                    'CATALOG_ID' => $catalogId,
                    'SKU' => $sku,
                    'CAMPAIGN_ID' => $campaignId,
                    'STATUS' => $archived
                        ? Storage\PlacementTable::STATUS_ARCHIVED
                        : Storage\PlacementTable::STATUS_PUBLISHED,
                ];
            }

            $assortment[] = [
                'CATALOG_ID' => $catalogId,
                'SKU' => $sku,
                'ELEMENT_ID' => $elementId,
                'CATEGORY_ID' => max($categoryId, 0),
                'STATUS' => Storage\AssortmentTable::STATUS_PLACED,
                'TIMESTAMP_X' => $now,
                'CREATED_AT' => $now,
            ];
        }

        return [ $assortment, $placement, $endpoints ];
    }

    private function compileEndpoints(array $endpoints)
    {
        $driver = new Catalog\Endpoint\Archive($this->processor->getSetup()->getBusinessId());
        $now = new Data\Type\CanonicalDateTime();

        foreach ($endpoints as &$endpoint)
        {
            $endpoint['STATUS'] = Storage\QueueTable::STATUS_WAIT;
            $endpoint['PRIORITY'] = $driver->priority(null, [
                $endpoint['ENDPOINT'] => $endpoint['PAYLOAD'],
            ]);
            $endpoint['TIMESTAMP_X'] = $now;
        }

        return $endpoints;
    }

    private function writeAssortment(array $assortment)
    {
        if (empty($assortment)) { return; }

        Storage\AssortmentTable::addBatch($assortment, [
            'CATEGORY_ID',
            'STATUS',
            'TIMESTAMP_X',
        ]);
    }

    private function writePlacement(array $placement)
    {
        if (empty($placement)) { return; }

        Storage\PlacementTable::addBatch($placement, true);
    }

    private function writeEndpoints(array $endpoints)
    {
        if (empty($endpoints)) { return; }

        Storage\QueueTable::addBatch($endpoints, true);
    }
}

