<?php
namespace Yandex\Market\Catalog\Run\Steps;

use Yandex\Market\Config;
use Yandex\Market\Data;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Utils\ArrayHelper;

class SkuFiller extends Data\Run\StepSkeleton
    implements Data\Run\StepClearable
{
    use Concerns\HasOnce;

    private $processor;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
    }

    public function getName()
    {
        return 'skuFiller';
    }

    public function clear()
    {
        $catalogId = $this->processor->getSetup()->getId();

        Catalog\Run\Storage\AssortmentTable::deleteBatch([
            'filter' => [ '=CATALOG_ID' => $catalogId ],
        ]);
        Catalog\Run\Storage\PlacementTable::deleteBatch([
            'filter' => [ '=CATALOG_ID' => $catalogId ],
        ]);
    }

    public function run($action, $offset = null)
    {
        $result = new Result\Step();
        $offsetObject = new Data\Run\Offset($offset);

		(new Data\Run\Waterfall())
		    ->add(new Transport\HttpCatcher($this->getName(), $this->processor->makeLogger()))
		    ->add([$this, 'iterateOffers'])
		    ->run($action, $offsetObject);

        if ($offsetObject->interrupted())
        {
            $result->setOffset((string)$offsetObject);
            $result->setTotal(1);

            if ($this->processor->getParameter('progressCount') === true)
            {
                $result->setReadyCount($this->readyCount());
            }
        }

        return $result;
    }

	public function iterateOffers(Data\Run\Waterfall $waterfall, $action, Data\Run\Offset $offset)
	{
		foreach ($this->offers($offset) as $offers)
		{
			list($offers, $deleteSkus) = $this->skuChange($offers);
			list($offers, $skuReplace) = $this->skuReplace($offers);
			$offerCampaigns = $this->offerCampaigns($offers);
			$deleteSkus = $this->deleteConflict($deleteSkus, ($action !== Catalog\Run\Processor::ACTION_CHANGE));
			$placement = $this->placement(array_merge(ArrayHelper::column($offers,'SKU'), array_keys($deleteSkus)));
			list($assortment, $offerEndpoints) = $this->compileOffers($offers, $offerCampaigns, $placement);
			list($deleteReplace, $deleteEndpoints) = $this->compileDelete($deleteSkus, $placement);

			$this->assortmentReplace(array_diff_key($skuReplace, $assortment) + $deleteReplace);
			$this->assortmentInsert($assortment);
			$this->endpointsInsert($this->compileEndpoints(array_merge($offerEndpoints, $deleteEndpoints)));
		}

		$waterfall->next($action, $offset);
	}

    private function readyCount()
    {
        return Catalog\Run\Storage\AssortmentTable::getCount([
            '=CATALOG_ID' => $this->processor->getSetup()->getId(),
            '>=TIMESTAMP_X' => $this->processor->getParameter('initTime'),
        ]);
    }

    private function offers(Data\Run\Offset $offset)
    {
        do
        {
            $rows = Storage\OfferTable::getList([
                'select' => [
                    'ELEMENT_ID',
                    'SKU',
                    'STATUS',
                    'ASSORTMENT_SKU' => 'ASSORTMENT.SKU',
                    'ASSORTMENT_STATUS' => 'ASSORTMENT.STATUS',
                    'ASSORTMENT_TIMESTAMP_X' => 'ASSORTMENT.TIMESTAMP_X',
                    'ASSORTMENT_CREATED_AT' => 'ASSORTMENT.CREATED_AT',
                ],
                'filter' => [
                    '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                    '>ELEMENT_ID' => (int)$offset->get('element'),
                    '>=TIMESTAMP_X' => $this->processor->getParameter('initTime'),
                    '!=STATUS' => Storage\OfferTable::STATUS_DUPLICATE,
                ],
                'limit' => 500,
                'order' => [ 'ELEMENT_ID' => 'ASC' ],
            ])->fetchAll();

            if (empty($rows)) { break; }

            yield $rows;

            $lastRow = end($rows);
            $offset->set('element', $lastRow['ELEMENT_ID']);

            if ($this->processor->isExpired())
            {
                $offset->interrupt();
                break;
            }
        }
        while (true);
    }

    private function skuChange(array $offers)
    {
        $activeSku = [];
        $deleteSku = [];

        foreach ($offers as $offerKey => &$offer)
        {
            $sku = (string)$offer['SKU'];
            $assortmentSku = (string)$offer['ASSORTMENT_SKU'];

            if ($sku !== '')
            {
                $activeSku[$sku] = true;
            }
            else
            {
                unset($offers[$offerKey]);
            }

            if ($assortmentSku === '') { continue; }

            if ($sku !== $assortmentSku)
            {
                $deleteSku[$assortmentSku] = $this->assortmentStatus($offer);

                $offer['ASSORTMENT_SKU'] = null;
                $offer['ASSORTMENT_STATUS'] = null;
                $offer['ASSORTMENT_TIMESTAMP_X'] = null;
                $offer['ASSORTMENT_CREATED_AT'] = null;
            }
        }
        unset($offer);

        return [
            $offers,
            array_diff_key($deleteSku, $activeSku),
        ];
    }

    private function deleteConflict(array $deleteSkus, $onlyChanged = false)
    {
        if (empty($deleteSkus)) { return []; }

        $filter = [
            '=CATALOG_ID' => $this->processor->getSetup()->getId(),
            '=SKU' => array_keys($deleteSkus),
            '=STATUS' => [
                Storage\OfferTable::STATUS_SUCCESS,
                Storage\OfferTable::STATUS_ERROR,
            ],
        ];

        if (!$onlyChanged)
        {
            $filter['>=TIMESTAMP_X'] = $this->processor->getParameter('initTime');
        }

        $query = Storage\OfferTable::getList([
            'filter' => $filter,
            'select' => [ 'SKU' ],
        ]);

        while ($row = $query->fetch())
        {
            if (isset($deleteSkus[$row['SKU']]))
            {
                unset($deleteSkus[$row['SKU']]);
            }
        }

        return $deleteSkus;
    }

    private function compileDelete(array $deleteSkus, array $placement)
    {
        $replaceSkus = [];
        $endpoints = [];

        foreach ($deleteSkus as $sku => $assortmentStatus)
        {
            $placementStatus = isset($placement[$sku][0]) ? $placement[$sku][0] : null;

            if ($placementStatus === Storage\PlacementTable::STATUS_PUBLISHED)
            {
                $endpoints[] = [
                    'CATALOG_ID' => $this->processor->getSetup()->getId(),
                    'SKU' => $sku,
                    'ENDPOINT' => Catalog\Glossary::ENDPOINT_ARCHIVE,
                    'CAMPAIGN_ID' => 0,
                    'PAYLOAD' => [ 'value' => true ],
                ];
            }

            $replaceSkus[$sku] = 0;
        }

        return [
            $replaceSkus,
            $endpoints,
        ];
    }

    private function skuReplace(array $offers)
    {
        $skuMap = $this->unmatchedSku($offers);

        if (empty($skuMap)) { return [ $offers, [] ]; }

        $replaces = [];

        $query = Storage\AssortmentTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=SKU' => array_keys($skuMap),
            ],
            'select' => [ 'ELEMENT_ID', 'SKU', 'STATUS', 'TIMESTAMP_X', 'CREATED_AT' ],
        ]);

        while ($row = $query->fetch())
        {
            if (!isset($skuMap[$row['SKU']])) { continue; }

            $offerKey = $skuMap[$row['SKU']];
            $offer = &$offers[$offerKey];

            $offer['ASSORTMENT_SKU'] = $row['SKU'];
            $offer['ASSORTMENT_STATUS'] = $row['STATUS'];
            $offer['ASSORTMENT_TIMESTAMP_X'] = $row['TIMESTAMP_X'];
            $offer['ASSORTMENT_CREATED_AT'] = $row['CREATED_AT'];

            if ($row['ELEMENT_ID'] > 0 && (int)$row['ELEMENT_ID'] !== (int)$offer['ELEMENT_ID'])
            {
                $replaces[$row['SKU']] = $row['ELEMENT_ID'];
            }

            unset($offer);
        }

        return [ $offers, $replaces ];
    }

    private function unmatchedSku(array $offers)
    {
        $result = [];

        foreach ($offers as $key => $offer)
        {
            $sku = (string)$offer['SKU'];
            $assortmentSku = (string)$offer['ASSORTMENT_SKU'];

            if ($sku === '' || $sku === $assortmentSku) { continue; }

            $result[$sku] = $key;
        }

        return $result;
    }

    private function offerCampaigns(array $offers)
    {
        $offerStatuses = array_column($offers, 'STATUS', 'ELEMENT_ID');

        if (empty($offerStatuses)) { return []; }

        $result = array_map(static function($status) {
            return [ 0 => ($status === Storage\OfferTable::STATUS_SUCCESS) ];
        }, $offerStatuses);

        $query = Storage\HashTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=ELEMENT_ID' => array_keys($offerStatuses),
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

    private function placement(array $skus)
    {
        if (empty($skus)) { return []; }

        $result = [];

        $query = Storage\PlacementTable::getList([
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=SKU' => $skus,
            ],
            'select' => [ 'SKU', 'CAMPAIGN_ID', 'STATUS' ],
        ]);

        while ($row = $query->fetch())
        {
            $sku = $row['SKU'];

            if (!isset($result[$sku])) { $result[$sku] = []; }

            $result[$sku][$row['CAMPAIGN_ID']] = $row['STATUS'];
        }

        return $result;
    }

    private function compileOffers(array $offers, array $offerCampaigns, array $placement)
    {
        $insert = [];
        $endpoints = [];
        $catalogId = $this->processor->getSetup()->getId();
        $now = new Data\Type\CanonicalDateTime();

        foreach ($offers as $offer)
        {
            $assortmentStatus = $this->assortmentStatus($offer);

            if ($assortmentStatus === Storage\AssortmentTable::STATUS_MISSING) { continue; }

            $skuPlacement = isset($placement[$offer['SKU']]) ? $placement[$offer['SKU']] : null;
            $offerEndpoints = [];

            foreach ($offerCampaigns[$offer['ELEMENT_ID']] as $campaignId => $offerValid)
            {
                if (
					$assortmentStatus === Storage\AssortmentTable::STATUS_UNKNOWN
					|| !isset($skuPlacement[$campaignId])
                )
                {
                    $offerEndpoints = [];
                    $insert[$offer['SKU']] = [
                        'CATALOG_ID' => $catalogId,
                        'SKU' => $offer['SKU'],
                        'ELEMENT_ID' => $offer['ELEMENT_ID'],
                        'STATUS' => Storage\AssortmentTable::STATUS_UNKNOWN,
                        'TIMESTAMP_X' => $now,
                        'CREATED_AT' => $offer['ASSORTMENT_CREATED_AT'] ?: $now,
                    ];
                    break;
                }

                $placementValid = ($skuPlacement[$campaignId] === Storage\PlacementTable::STATUS_PUBLISHED);

                if ($placementValid !== $offerValid)
                {
                    $offerEndpoints[] = [
                        'CATALOG_ID' => $catalogId,
                        'SKU' => $offer['SKU'],
                        'ENDPOINT' => Catalog\Glossary::ENDPOINT_ARCHIVE,
                        'CAMPAIGN_ID' => $campaignId,
                        'PAYLOAD' => [ 'value' => !$offerValid ],
                    ];
                }
            }

            if (!empty($offerEndpoints))
            {
                array_push($endpoints, ...$offerEndpoints);
            }
        }

        return [ $insert, $endpoints ];
    }

    private function assortmentStatus(array $offer)
    {
        if ((string)$offer['ASSORTMENT_STATUS'] === '')
        {
            return Storage\AssortmentTable::STATUS_UNKNOWN;
        }

        if ($offer['ASSORTMENT_STATUS'] !== Storage\AssortmentTable::STATUS_MISSING || $this->processor->getSetup()->isOfferEnabled())
        {
            return $offer['ASSORTMENT_STATUS'];
        }

        if ($this->isMissingExpired($offer['ASSORTMENT_TIMESTAMP_X'], $offer['ASSORTMENT_CREATED_AT']))
        {
            return Storage\AssortmentTable::STATUS_UNKNOWN;
        }

        return Storage\AssortmentTable::STATUS_MISSING;
    }

    private function isMissingExpired(Data\Type\CanonicalDateTime $timestampX, Data\Type\CanonicalDateTime $createdAt)
    {
        static $expire = [];

        $hours = $this->assortmentJustCreated($createdAt)
            ? (int)Config::getOption('catalog_created_expire', 2)
            : (int)Config::getOption('catalog_missing_expire', 48);

        if (!isset($expire[$hours]))
        {
            $expire[$hours] = new Data\Type\CanonicalDateTime();

            if ($hours > 0)
            {
                $expire[$hours]->add("-PT{$hours}H");
            }
        }

        return Data\DateTime::compare($timestampX, $expire[$hours]) !== 1;
    }

    private function assortmentJustCreated(Data\Type\CanonicalDateTime $createdAt)
    {
        static $expire = null;

        if ($expire === null)
        {
            $hours = max(0, (int)Config::getOption('catalog_just_created', 6));
            $expire = new Data\Type\CanonicalDateTime();

            if ($hours > 0)
            {
                $expire->add("-PT{$hours}H");
            }
        }

        return Data\DateTime::compare($createdAt, $expire) !== -1;
    }

    private function assortmentInsert(array $assortment)
    {
        if (empty($assortment)) { return; }

        Storage\AssortmentTable::addBatch($assortment, true);
    }

    private function assortmentReplace(array $replaces)
    {
        if (empty($replaces)) { return; }

        $rows = [];
        $catalogId = $this->processor->getSetup()->getId();

        foreach ($replaces as $sku => $elementId)
        {
            $rows[] = [
                'CATALOG_ID' => $catalogId,
                'SKU' => $sku,
                'ELEMENT_ID' => $elementId,
            ];
        }

        Storage\AssortmentTable::addBatch($rows, true);
    }

    private function compileEndpoints(array $endpoints)
    {
	    $driverCache = [];
        $businessId = $this->processor->getSetup()->getBusinessId();
        $now = new Data\Type\CanonicalDateTime();

        foreach ($endpoints as &$endpoint)
        {
	        $driverKey = "{$endpoint['ENDPOINT']}:{$endpoint['CAMPAIGN_ID']}";

			if (isset($driverCache[$driverKey]))
			{
				$driver = $driverCache[$driverKey];
			}
			else
			{
                $driver = Catalog\Endpoint\Registry::restore($endpoint['ENDPOINT'], $businessId, $endpoint['CAMPAIGN_ID']);
				$driverCache[$driverKey] = $driver;
			}

            $endpoint['STATUS'] = Storage\QueueTable::STATUS_WAIT;
            $endpoint['PRIORITY'] = $driver->priority(null, [
                $endpoint['ENDPOINT'] => $endpoint['PAYLOAD'],
            ]);
            $endpoint['TIMESTAMP_X'] = $now;
        }
        unset($endpoint);

        return $endpoints;
    }

    private function endpointsInsert(array $endpoints)
    {
        if (empty($endpoints)) { return; }

        Storage\QueueTable::addBatch($endpoints, true);
    }
}

