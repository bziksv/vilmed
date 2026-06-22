<?php
namespace Yandex\Market\Catalog\Run\Steps\Downloader;

use Bitrix\Main;
use Yandex\Market\Api\Business\OfferMappings;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data;
use Yandex\Market\Catalog;

class UnknownActualizer
{
    protected $processor;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
    }

    public function run(Data\Run\Offset $offset)
    {
        foreach ($this->assortment($offset) as $stored)
        {
            $offerMappings = $this->offerMappings($stored);
            $offerCampaigns = $this->offerCampaigns($stored);
            list($assortment, $placement, $endpoints) = $this->compile($offerCampaigns, $offerMappings);

            $this->writeAssortment($assortment);
            $this->writePlacement($placement);
            $this->writeEndpoints($endpoints);
        }
    }

    private function assortment(Data\Run\Offset $offset)
    {
        do
        {
            $rows = Storage\AssortmentTable::getList([
                'filter' => [
                    '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                    '=STATUS' => Storage\AssortmentTable::STATUS_UNKNOWN,
                ],
                'select' => [ 'SKU', 'ELEMENT_ID', 'OFFER_STATUS' => 'OFFER.STATUS' ],
                'limit' => OfferMappings\Request::OFFERS_LIMIT,
            ])->fetchAll();

            if (empty($rows)) { break; }

            yield $rows;

            if (count($rows) < OfferMappings\Request::OFFERS_LIMIT) { break; }

            if ($this->processor->isExpired())
            {
                $offset->interrupt();
                break;
            }
        }
        while (true);
    }

    private function offerMappings(array $assortment)
    {
        /** @var OfferMappings\Request $request */
	    $business = $this->processor->getSetup()->getBusiness();
        $request = new OfferMappings\Request($business->getId(), $business->getOptions()->getApiAuth(), $business->createLogger());
        $request->setOfferIds(array_column($assortment, 'SKU'));

        $response = $request->execute();

        if ($response->getPaging()->hasNext())
        {
            throw new Main\SystemException('unknown strategy for download assortment does not support market paging');
        }

        return $response->getOfferMappings();
    }

    private function offerCampaigns(array $assortment)
    {
        $result = array_map(
            static function($status) { return [ 0 => ($status === Storage\OfferTable::STATUS_SUCCESS) ]; },
            array_column($assortment, 'OFFER_STATUS', 'SKU')
        );
        $skuMap = array_column($assortment, 'SKU', 'ELEMENT_ID');
        $skuMap = array_diff_key($skuMap, [ 0 => true ]);

        if (empty($skuMap)) { return []; }

        $query = Storage\HashTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=ELEMENT_ID' => array_keys($skuMap),
                '>CAMPAIGN_ID' => 0,
            ],
            'select' => [ 'ELEMENT_ID', 'CAMPAIGN_ID', 'STATUS' ],
        ]);

        while ($row = $query->fetch())
        {
            $elementId = (int)$row['ELEMENT_ID'];
            $campaignId = (int)$row['CAMPAIGN_ID'];
            $valid = ($row['STATUS'] === Storage\HashTable::STATUS_SUCCESS);

            if (!isset($skuMap[$elementId])) { continue; }

            $sku = $skuMap[$elementId];

            if (!$valid || !isset($result[$sku][$campaignId]))
            {
                $result[$sku][$campaignId] = $valid;
            }
        }

        return $result;
    }

    private function compile(array $offerCampaigns, OfferMappings\OfferMappingCollection $offerMappings)
    {
        $catalog = $this->processor->getSetup();
        $catalogId = $catalog->getId();
        $driver = new Catalog\Endpoint\Archive($catalog->getBusinessId());
        $now = new Data\Type\CanonicalDateTime();
        $assortment = [];
        $placement = [];
        $endpoints = [];

        foreach ($offerCampaigns as $sku => $skuCampaigns)
        {
            $offerMapping = $offerMappings->getItemByOfferId($sku);

            if ($offerMapping === null)
            {
                $assortment[] = [
                    'CATALOG_ID' => $catalogId,
                    'SKU' => $sku,
                    'CATEGORY_ID' => 0,
                    'STATUS' => Storage\AssortmentTable::STATUS_MISSING,
                    'TIMESTAMP_X' => $now,
                ];
                continue;
            }

            $marketCategoryId = $offerMapping->getMapping()->getMarketCategoryId();

            $assortment[] = [
                'CATALOG_ID' => $catalogId,
                'SKU' => $sku,
                'CATEGORY_ID' => max($marketCategoryId, 0),
                'STATUS' => Storage\AssortmentTable::STATUS_PLACED,
                'TIMESTAMP_X' => $now,
            ];

            foreach ($skuCampaigns as $campaignId => $valid)
            {
                $archived = $offerMapping->getOffer()->isArchived($campaignId);

                if ($archived !== !$valid)
                {
                    $payload = [ 'value' => !$valid ];

                    $endpoints[] = [
                        'CATALOG_ID' => $catalogId,
                        'SKU' => $sku,
                        'ENDPOINT' => Catalog\Glossary::ENDPOINT_ARCHIVE,
                        'CAMPAIGN_ID' => $campaignId,
                        'PAYLOAD' => $payload,
                        'STATUS' => Storage\QueueTable::STATUS_WAIT,
                        'PRIORITY' => $driver->priority(null, [
                            Catalog\Glossary::ENDPOINT_ARCHIVE => $payload,
                        ]),
                        'TIMESTAMP_X' => $now,
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
        }

        return [ $assortment, $placement, $endpoints ];
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

