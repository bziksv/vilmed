<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Bitrix\Main;
use Yandex\Market\Data\Run\Waterfall;
use Yandex\Market\Export\Xml\Tag;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Utils\ArrayHelper;

class TasksBuilder
{
	use Concerns\HasMessage;

    public function __invoke(Waterfall $waterfall, State $state)
    {
        $catalogId = $state->catalog->getId();
        $endpointCollection = $state->catalogProduct->getEndpointCollection();

        list($hashRows, $tasks, $errorElements) = $this->compile($state->tagNodes, $state->offers, $catalogId, $endpointCollection, $state->logger);
        list($hashChanged, $hashUnchanged, $hashDelete) = $this->splitStored($hashRows, $state->offers, $catalogId);
	    $hashUnchanged = array_filter($hashUnchanged, static function(array $hashRow) { return $hashRow['STATUS'] === Storage\HashTable::STATUS_SUCCESS; });
	    $hashChanged += $this->changedSku($hashUnchanged, $state->offers, $catalogId);
	    $hashUnchanged = array_diff_key($hashUnchanged, $hashChanged);
		$hashChanged += $this->expiredHash($hashUnchanged, $state->offers, $catalogId, $endpointCollection);

        $state->offers = $this->markOffersError($state->offers, $errorElements);

        $this->insertOffers($state->offers);
        $this->insertHash($hashChanged);
        $this->deleteHash($hashDelete);

        $state = clone $state;
        $state->tasks = $tasks;
        $state->hashChanged = $hashChanged;

	    $waterfall->next($state);
    }

    private function compile(array $tagNodes, array $offers, $catalogId, Catalog\Endpoint\EndpointCollection $endpointCollection, LoggerInterface $logger)
    {
        $hashRows = [];
        $tasks = [];
        $errorElements = [];

        foreach ($tagNodes as $endpointKey => $endpointNodes)
        {
			$endpoint = $endpointCollection->requireItem($endpointKey);
			$driver = $endpoint->getDriver();
            $endpointPrimary = $endpoint->getPrimary();
            $endpointPrimaryString = implode(':', array_filter([
                $endpointPrimary->getType(),
                $endpointPrimary->getPart() !== Storage\HashTable::PART_COMMON ? $endpointPrimary->getPart() : null,
            ]));
			$indirectKeys = $this->indirectKeys($endpoint->getTagBundle()->getTag());

            /** @var Result\XmlNode $tagNode */
            foreach ($endpointNodes as $elementId => $tagNode)
            {
                $offer = $offers[$elementId];
                $hashRow = [
                    'CATALOG_ID' => $catalogId,
                    'ELEMENT_ID' => $elementId,
                    'CAMPAIGN_ID' => $endpointPrimary->getCampaignId(),
                    'ENDPOINT_KEY' => $endpointPrimaryString,
                    'STATUS' => null,
                    'HASH' => null,
                ];
                $sign = $this->rowSign($hashRow);

                if ($tagNode->isSuccess())
                {
                    $payload = $tagNode->getExportElement()->build();
					$payload = $this->sanitizeIndirect($payload, $indirectKeys);

	                if (empty($payload) || !is_array($payload)) { continue; }

                    $hashRow['STATUS'] = Storage\HashTable::STATUS_SUCCESS;
                    $hashRow['HASH'] = $this->hashPayload($payload);

                    $tasks[$sign] = [
                        'CATALOG_ID' => $catalogId,
                        'SKU' => $offer['SKU'],
                        'ENDPOINT' => $endpointPrimary->getType(),
                        'CAMPAIGN_ID' => $endpointPrimary->getCampaignId(),
                        'PAYLOAD' => $payload,
                        'STATUS' => Storage\QueueTable::STATUS_WAIT,
                    ];
                }
                else
                {
					if (!$tagNode->hasErrors() && $tagNode->getExportElement() === null) { continue; }

                    $hashRow['STATUS'] = Storage\HashTable::STATUS_ERROR;
                    $hashRow['HASH'] = '';

                    if ($endpointPrimary->getCampaignId() === 0)
                    {
                        $errorElements[$elementId] = $elementId;
                    }

					$logContext = [
						'ENTITY_TYPE' => Catalog\Glossary::ENTITY_OFFER,
						'ENTITY_ID' => $elementId,
						'CAMPAIGN_ID' => $endpointPrimary->getCampaignId(),
						'AUDIT' => $driver->audit(),
					];

					if ($tagNode->hasErrors())
					{
						$logger->error($tagNode, $logContext);
					}
					else
					{
						$logger->notice(self::getMessage('INVALIDATED'), $logContext);
					}
                }

                $hashRows[$sign] = $hashRow;

	            if ($driver instanceof Catalog\Endpoint\DriverWithCampaignGroup)
	            {
		            foreach ($driver->campaignGroup() as $groupCampaignId)
		            {
			            $groupRow = $hashRow;
			            $groupRow['CAMPAIGN_ID'] = $groupCampaignId;

			            $hashRows[$this->rowSign($groupRow)] = $groupRow;
					}
	            }
            }
        }

        return [ $hashRows, $tasks, $errorElements ];
    }

	private function indirectKeys(Tag\Base $tag)
	{
		$result = [];

		foreach ($tag->getChildren() as $child)
		{
			if ($child->getParameter('indirect') === true)
			{
				$result[$child->getId()] = true;
			}
		}

		return $result;
	}

	private function sanitizeIndirect($payload, array $indirectKeys)
	{
		if (!is_array($payload) || empty($indirectKeys)) { return $payload; }

		foreach ($payload as $name => $value)
		{
			if (!isset($indirectKeys[$name]))
			{
				return $payload;
			}
		}

		return null;
	}

    private function hashPayload(array $payload)
    {
        $payloadEncoded = Main\Web\Json::encode($payload);

        if (mb_strlen($payloadEncoded) > Storage\HashTable::HASH_LENGTH)
        {
            return md5($payloadEncoded);
        }

        return $payloadEncoded;
    }

    private function splitStored(array $rows, array $offers, $catalogId)
    {
        $stored = $this->storedHash($offers, $catalogId);

	    $changed = array_filter($rows, static function($row, $sign) use ($stored) {
		    return (
				!isset($stored[$sign])
			    || $stored[$sign]['STATUS'] !== $row['STATUS']
			    || $stored[$sign]['HASH'] !== $row['HASH']
		    );
	    }, ARRAY_FILTER_USE_BOTH);
		$unchanged = array_diff_key($rows, $changed);
	    $delete = array_diff_key($stored, $rows);

        return [$changed, $unchanged, $delete];
    }

    private function storedHash(array $offers, $catalogId)
    {
        if (empty($offers)) { return []; }

        $result = [];

        $query = Storage\HashTable::getList([
            'filter' => [
                '=CATALOG_ID' => $catalogId,
                '=ELEMENT_ID' => array_values(array_column($offers, 'ELEMENT_ID', 'ELEMENT_ID')),
            ],
        ]);

        while ($row = $query->fetch())
        {
            $result[$this->rowSign($row)] = $row;
        }

        return $result;
    }

	private function changedSku(array $hashRows, array $offers, $catalogId)
	{
		$storedSku = $this->storedSku($hashRows, $catalogId);

		return array_filter($hashRows, static function(array $row) use ($storedSku, $offers) {
			return (
				!isset($storedSku[$row['ELEMENT_ID']])
				|| ((string)$storedSku[$row['ELEMENT_ID']] !== (string)$offers[$row['ELEMENT_ID']]['SKU'])
			);
		});
	}

	private function storedSku(array $hashRows, $catalogId)
	{
		if (empty($hashRows)) { return []; }

		$result = [];

		$query = Storage\OfferTable::getList([
			'filter' => [
				'=CATALOG_ID' => $catalogId,
				'=ELEMENT_ID' => array_values(array_column($hashRows, 'ELEMENT_ID', 'ELEMENT_ID')),
			],
			'select' => [ 'ELEMENT_ID', 'SKU' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['ELEMENT_ID']] = $row['SKU'];
		}

		return $result;
	}

	private function expiredHash(array $hashRows, array $offers, $catalogId, Catalog\Endpoint\EndpointCollection $endpointCollection)
	{
		$expiredQueue = $this->expiredQueue($hashRows, $offers, $catalogId, $endpointCollection);

		return array_filter($hashRows, static function(array $hashRow) use ($expiredQueue) {
			if (!isset($expiredQueue[$hashRow['ELEMENT_ID']])) { return false; }

			$expiredSku = $expiredQueue[$hashRow['ELEMENT_ID']];

			list($type) = explode(':', $hashRow['ENDPOINT_KEY'], 2);
			$key = "{$type}:{$hashRow['CAMPAIGN_ID']}";

			return isset($expiredSku[$key]);
		});
	}

	private function expiredQueue(array $hashRows, array $offers, $catalogId, Catalog\Endpoint\EndpointCollection $endpointCollection)
	{
		$expiryEndpointFilter = $this->expiryEndpointFilter($endpointCollection, $this->usedEndpoints($hashRows));

		if ($expiryEndpointFilter === null) { return []; }

		$offers = array_intersect_key($offers, array_column($hashRows, 'ELEMENT_ID', 'ELEMENT_ID'));
		$skuMap = array_column($offers, 'ELEMENT_ID', 'SKU');
		$result = [];

		$query = Storage\QueueTable::getList([
			'filter' => [
				'=CATALOG_ID' => $catalogId,
				'=SKU' => array_keys($skuMap),
				$expiryEndpointFilter
			],
			'select' => [ 'SKU', 'ENDPOINT', 'CAMPAIGN_ID' ],
		]);

		while ($queueRow = $query->fetch())
		{
			if (!isset($skuMap[$queueRow['SKU']])) { continue; }

			$elementId = $skuMap[$queueRow['SKU']];

			if (!isset($result[$elementId])) { $result[$elementId] = []; }

			$result[$elementId]["{$queueRow['ENDPOINT']}:{$queueRow['CAMPAIGN_ID']}"] = true;
		}

		return $result;
	}

	private function usedEndpoints(array $hashRows)
	{
		$endpointKeys = array_column($hashRows, 'ENDPOINT_KEY', 'ENDPOINT_KEY');
		$result = [];

		foreach ($endpointKeys as $endpointKey)
		{
			list($type) = explode(':', $endpointKey, 2);

			$result[$type] = true;
		}

		return $result;
	}

	private function expiryEndpointFilter(Catalog\Endpoint\EndpointCollection $endpointCollection, array $usedEndpoints)
	{
		if (empty($usedEndpoints)) { return null; }

		$partials = [];

		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($endpointCollection as $endpoint)
		{
			$driver = $endpoint->getDriver();
			$type = $driver->type();

			if (
				isset($partials[$type])
				|| !isset($usedEndpoints[$type])
				|| !($driver instanceof Catalog\Endpoint\DriverWithExpiryDate)
			)
			{
				continue;
			}

			$partials[$type] = [
				'=ENDPOINT' => $driver->type(),
				'<TIMESTAMP_X' => $driver->expiryDate(),
			];
		}

		if (empty($partials)) { return null; }

		if (count($partials) === 1)
		{
			return reset($partials);
		}

		return [ 'LOGIC' => 'OR' ] + array_values($partials);
	}

    private function rowSign(array $row)
    {
        return "{$row['ELEMENT_ID']}:{$row['CAMPAIGN_ID']}:{$row['ENDPOINT_KEY']}";
    }

    private function markOffersError(array $offers, array $errorElements)
    {
        foreach ($errorElements as $elementId)
        {
            $offers[$elementId]['STATUS'] = Storage\OfferTable::STATUS_ERROR;
        }

        return $offers;
    }

    private function insertOffers(array $offers)
    {
        if (empty($offers)) { return; }

        Storage\OfferTable::addBatch($offers, true);
    }

    private function insertHash(array $rows)
    {
        if (empty($rows)) { return; }

        Storage\HashTable::addBatch($rows, true);
    }

    private function deleteHash(array $rows)
    {
        foreach (ArrayHelper::groupByComposite($rows, [ 'CAMPAIGN_ID', 'ENDPOINT_KEY' ]) as $endpointRows)
        {
            $firstRow = reset($endpointRows);

            Storage\HashTable::deleteBatch([
                'filter' => [
                    '=CATALOG_ID' => $firstRow['CATALOG_ID'],
                    '=ELEMENT_ID' => array_column($endpointRows, 'ELEMENT_ID'),
                    '=CAMPAIGN_ID' => $firstRow['CAMPAIGN_ID'],
                    '=ENDPOINT_KEY' => $firstRow['ENDPOINT_KEY'],
                ],
            ]);
        }
    }
}