<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Data;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Catalog\Glossary;

class AssortmentCommiter
{
    private $catalogId;

    public function __construct($catalogId)
    {
        $this->catalogId = $catalogId;
    }

    public function write(array $changes, array $assortment, $priority)
    {
        $changes = $this->sanitize($changes, $assortment);

        foreach ($this->compileAssortment($changes) as $rows)
        {
			$wasPlaced = $this->wasPlaced($rows);
			$changedCategoryId = $this->changedCategoryId($rows);

			$this->insertAssortment($rows);
	        $this->activateQueue($wasPlaced);
			$this->hideErrorCampaigns($wasPlaced, $assortment, $priority);
			$this->repeatCardEndpoint($changedCategoryId);
		}
    }

    private function sanitize(array $changes, array $assortment)
    {
		$changes = array_intersect_key($changes, $assortment);

        foreach ($changes as $sku => &$change)
        {
			foreach ($change as $field => $value)
			{
				if (!isset($assortment[$sku][$field])) { continue; }

				if ((string)$value === (string)$assortment[$sku][$field])
				{
					unset($change[$field]);
				}
			}
        }
        unset($change);

        return $changes;
    }

    private function compileAssortment(array $assortment)
    {
        $insertGroups = [];
        $now = new Data\Type\CanonicalDateTime();

        foreach ($assortment as $sku => $change)
        {
            if (empty($change)) { continue; }

            $groupKey = implode(':', array_keys($change));

            if (!isset($insertGroups[$groupKey])) { $insertGroups[$groupKey] = []; }

            $insert = [
                'CATALOG_ID' => $this->catalogId,
                'SKU' => $sku,
            ];
            $insert += $change;
            $insert['TIMESTAMP_X'] = $now;

            $insertGroups[$groupKey][] = $insert;
        }

        return $insertGroups;
    }

	private function insertAssortment(array $rows)
	{
		if (empty($rows)) { return; }

		Storage\AssortmentTable::addBatch($rows, true);
	}

	private function wasPlaced(array $rows)
	{
		$result = [];

		foreach ($rows as $row)
		{
			if (
				isset($row['STATUS'])
				&& $row['STATUS'] === Storage\AssortmentTable::STATUS_PLACED
			)
			{
				$result[] = $row['SKU'];
			}
		}

		return $result;
	}

	private function changedCategoryId(array $rows)
	{
		$result = [];

		foreach ($rows as $row)
		{
			if (!empty($row['CATEGORY_ID']))
			{
				$result[] = $row['SKU'];
			}
		}

		return $result;
	}

	private function activateQueue(array $skus)
	{
		if (empty($skus)) { return; }

		Storage\QueueTable::updateBatch([
			'filter' => [
				'=CATALOG_ID' => $this->catalogId,
				'=SKU' => $skus,
				'=STATUS' => Storage\QueueTable::STATUS_MISSING,
			],
		], [
			'STATUS' => Storage\QueueTable::STATUS_WAIT,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}

	private function hideErrorCampaigns(array $skus, array $assortment, $priority)
	{
		$endpoints = [];
		$skuMap = array_column($assortment, 'ELEMENT_ID', 'SKU');
		$elementMap = array_filter(array_intersect_key($skuMap, array_flip($skus)));

		foreach ($this->errorCampaigns(array_values($elementMap)) as $hashRow)
		{
			if (!isset($skuMap[$hashRow['ELEMENT_ID']])) { continue; }

			$endpoints[] = [
				'CATALOG_ID' => $this->catalogId,
				'SKU' => $skuMap[$hashRow['ELEMENT_ID']],
				'ENDPOINT' => Glossary::ENDPOINT_ARCHIVE,
				'CAMPAIGN_ID' => $hashRow['CAMPAIGN_ID'],
				'PAYLOAD' => [ 'value' => true ],
				'STATUS' => Storage\QueueTable::STATUS_WAIT,
				'PRIORITY' => $priority + 1,
				'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
			];
		}

		$this->writeEndpoints($endpoints);
	}

	private function errorCampaigns(array $elementIds)
	{
		if (empty($elementIds)) { return []; }

		$query = Storage\HashTable::getList([
			'filter' => [
				'=CATALOG_ID' => $this->catalogId,
				'=ELEMENT_ID' => $elementIds,
				'>CAMPAIGN_ID' => 0,
				'!=ENDPOINT_KEY' => Glossary::ENDPOINT_STOCKS,
				'=STATUS' => Storage\HashTable::STATUS_ERROR,
			],
			'select' => [ 'ELEMENT_ID', 'CAMPAIGN_ID' ],
		]);

		return $query->fetchAll();
	}

	private function writeEndpoints(array $endpoints)
	{
		if (empty($endpoints)) { return; }

		Storage\QueueTable::addBatch($endpoints);
	}

	private function repeatCardEndpoint(array $skus)
	{
		if (empty($skus)) { return; }

		Storage\QueueTable::updateBatch([
			'filter' => [
				'=CATALOG_ID' => $this->catalogId,
				'=SKU' => $skus,
				'=ENDPOINT' => Glossary::ENDPOINT_CARD,
				'=STATUS' => Storage\QueueTable::STATUS_SUCCESS,
			],
		], [
			'STATUS' => Storage\QueueTable::STATUS_WAIT,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}
}