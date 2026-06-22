<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Catalog\Run\Storage;

class PlacementCommiter
{
    private $catalogId;

    public function __construct($catalogId)
    {
        $this->catalogId = $catalogId;
    }

    public function write(array $changes, array $assortment, $campaignId)
    {
        $changes = array_intersect_key($changes, $assortment);

        foreach ($this->compileRows($changes, $campaignId) as $insert)
        {
            Storage\PlacementTable::addBatch($insert, true);
        }
    }

    private function compileRows(array $changes, $campaignId)
    {
        $insertGroups = [];
        $catalogId = $this->catalogId;

        foreach ($changes as $sku => $fields)
        {
            if (empty($fields)) { continue; }

            $groupKey = implode(':', array_keys($fields));

            if (!isset($insertGroups[$groupKey])) { $insertGroups[$groupKey] = []; }

            $insert = [
                'CATALOG_ID' => $catalogId,
                'SKU' => $sku,
                'CAMPAIGN_ID' => $campaignId,
            ];
            $insert += $fields;

            $insertGroups[$groupKey][] = $insert;
        }

        return $insertGroups;
    }
}