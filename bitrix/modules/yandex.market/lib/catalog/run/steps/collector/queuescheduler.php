<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Yandex\Market\Catalog\Endpoint\Registry;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data;
use Yandex\Market\Utils\ArrayHelper;

class QueueScheduler
{
    public function __invoke(Data\Run\Waterfall $waterfall, State $state)
    {
        $tasks = $this->combineTasks($state->tasks);
        $stored = $this->stored($tasks);
        $waiting = array_filter($stored, static function(array $row) { return $row['STATUS'] === Storage\QueueTable::STATUS_WAIT; });
        $missing = array_diff_key($waiting, $tasks);
        $waiting = array_diff_key($waiting, $missing);

        $changed = array_intersect_key($state->tasks, $state->hashChanged);
        $changed = $this->combineTasks($changed);
        $changed = $this->combineTasks(array_intersect_key($waiting, $changed), $changed);
        $changed = $this->fillPriority(
            $changed,
            $this->placementStatuses($changed),
            $this->groupSubmittable($tasks),
            $this->groupSubmittable($stored),
            $state->catalog->getBusinessId()
        );

        $this->queueNew($changed);
        $this->deleteMissing($missing);

	    $waterfall->next($state);
    }

    private function stored(array $tasks)
    {
        if (empty($tasks)) { return []; }

        $firstTask = reset($tasks);
        $result = [];

        $query = Storage\QueueTable::getList([
            'filter' => [
                '=CATALOG_ID' => $firstTask['CATALOG_ID'],
                '=SKU' => array_values(array_column($tasks, 'SKU', 'SKU')),
                '=ENDPOINT' => array_values(array_column($tasks, 'ENDPOINT', 'ENDPOINT')),
                '=CAMPAIGN_ID' => array_values(array_column($tasks, 'CAMPAIGN_ID', 'CAMPAIGN_ID')),
                '=STATUS' => [
                    Storage\QueueTable::STATUS_WAIT,
                    Storage\QueueTable::STATUS_SUCCESS,
                ],
            ],
            'select' => [ 'CATALOG_ID', 'SKU', 'ENDPOINT', 'CAMPAIGN_ID', 'STATUS', 'PAYLOAD' ],
        ]);

        while ($row = $query->fetch())
        {
            $result[$this->taskKey($row)] = $row;
        }

        return $result;
    }

    private function taskKey(array $row)
    {
        return "{$row['SKU']}:{$row['ENDPOINT']}:{$row['CAMPAIGN_ID']}";
    }

    private function groupSubmittable(array $tasks)
    {
        $result = [];

        foreach ($tasks as $task)
        {
            if ($task['STATUS'] === Storage\QueueTable::STATUS_ERROR) { continue; }

            $campaignId = $task['CAMPAIGN_ID'];
            $sku = $task['SKU'];

            if (!isset($result[$campaignId])) { $result[$campaignId] = []; }
            if (!isset($result[$campaignId][$sku])) { $result[$campaignId][$sku] = []; }

            $result[$campaignId][$sku][$task['ENDPOINT']] = $task['PAYLOAD'];
        }

        return $result;
    }

    private function placementStatuses(array $tasks)
    {
        if (empty($tasks)) { return []; }

        $firstTask = reset($tasks);
        $result = [];

        $query = Storage\PlacementTable::getList([
            'filter' => [
                '=CATALOG_ID' => $firstTask['CATALOG_ID'],
                '=SKU' => array_values(array_column($tasks, 'SKU', 'SKU')),
                '=CAMPAIGN_ID' => array_values(array_column($tasks, 'CAMPAIGN_ID', 'CAMPAIGN_ID')),
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

    private function combineTasks(array $tasks, array $previous = [])
    {
        $result = $previous;

        foreach ($tasks as $task)
        {
            $key = $this->taskKey($task);

            if (isset($result[$key]))
            {
                $result[$key]['PAYLOAD'] += $task['PAYLOAD'];
                continue;
            }

            $result[$key] = $task;
        }

        return $result;
    }

    private function fillPriority(array $tasks, array $placementStatuses, array $prepared, array $submitted, $businessId)
    {
        $driverCache = [];

        foreach ($tasks as &$task)
        {
            $driverKey = "{$task['ENDPOINT']}:{$task['CAMPAIGN_ID']}";
            $skuStatus = isset($placementStatuses[$task['SKU']][$task['CAMPAIGN_ID']])
                ? $placementStatuses[$task['SKU']][$task['CAMPAIGN_ID']]
                : null;
            $skuPrepared = $prepared[$task['CAMPAIGN_ID']][$task['SKU']];
            $skuSubmitted = isset($submitted[$task['CAMPAIGN_ID']][$task['SKU']]) ? $submitted[$task['CAMPAIGN_ID']][$task['SKU']] : null;

            if (isset($driverCache[$driverKey]))
            {
                $driver = $driverCache[$driverKey];
            }
            else
            {
                $driver = Registry::restore($task['ENDPOINT'], $businessId, $task['CAMPAIGN_ID']);
                $driverCache[$driverKey] = $driver;
            }

            $task['PRIORITY'] = $driver->priority($skuStatus, $skuPrepared, $skuSubmitted);
        }
        unset($task);

        return $tasks;
    }

    private function queueNew(array $tasks)
    {
        if (empty($tasks)) { return; }

        $rows = array_map(static function(array $task) {
            $task['PREPARED'] = '';
            $task['TIMESTAMP_X'] = new Data\Type\CanonicalDateTime();

            return $task;
        }, $tasks);

        Storage\QueueTable::addBatch($rows, true);
    }

    private function deleteMissing(array $stored)
    {
        foreach (ArrayHelper::groupByComposite($stored, [ 'ENDPOINT', 'CAMPAIGN_ID' ]) as $group)
        {
            $first = reset($group);

            Storage\QueueTable::deleteBatch([
                'filter' => [
                    '=CATALOG_ID' => $first['CATALOG_ID'],
                    '=SKU' => array_column($group, 'SKU'),
                    '=ENDPOINT' => $first['ENDPOINT'],
                    '=CAMPAIGN_ID' => $first['CAMPAIGN_ID'],
                ],
            ]);
        }
    }
}