<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Config;
use Yandex\Market\Data;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Logger\Level;
use Yandex\Market\Logger\Reference\Logger;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Reference\Concerns;

class TasksFetcher
{
	use Concerns\HasMessage;
	use Concerns\HasOnce;

    private $processor;
    private $queueCommiter;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
	    $this->queueCommiter = new QueueCommiter($processor->getSetup()->getId());
    }

    public function __invoke(Data\Run\Waterfall $waterfall, Data\Run\Offset $offset)
    {
        foreach ($this->endpoints($offset) as list($driver, $priority))
        {
	        $logger = $this->makeLogger($driver);
            $needPrepare = ($driver instanceof Catalog\Endpoint\DriverWithPrepareCategory);
	        list($queue, $assortment) = $this->tasks($driver, $priority, $needPrepare, $logger);

	        $waterfall->next($driver, $queue, $assortment, $offset, $logger);

	        $logger->flush();

            if ($offset->interrupted()) { return; }
        }
    }

    private function endpoints(Data\Run\Offset $offset)
    {
        do
        {
            $row = Storage\QueueTable::getRow([
                'select' => [ 'ENDPOINT', 'CAMPAIGN_ID', 'PRIORITY' ],
                'filter' => [
                    '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                    '=STATUS' => Storage\QueueTable::STATUS_WAIT,
					'>=PRIORITY' => (int)$offset->get('priority'),
	                '!=ASSORTMENT.STATUS' => Storage\AssortmentTable::STATUS_UNKNOWN, // parallel execute
                ],
                'order' => [ 'PRIORITY' => 'ASC', 'CAMPAIGN_ID' => 'ASC' ],
            ]);

            if ($row === null) { break; }

            $driver = Catalog\Endpoint\Registry::restore($row['ENDPOINT'], $this->processor->getSetup()->getBusinessId(), (int)$row['CAMPAIGN_ID']);
	        $priority = (int)$row['PRIORITY'];

	        $offset->override('priority', $priority); // pass to submitter

            yield [$driver, $priority];

			$offset->set('priority', $priority); // reset next
        }
        while (true);
    }

	private function makeLogger(Catalog\Endpoint\Driver $driver)
	{
		$audit = $driver->audit();

		$logger = $this->processor->makeLogger();
		$logger->setContext('CAMPAIGN_ID', $driver->campaignId());
		$logger->setContext('AUDIT', $audit);
		$logger->allowBatch();
		$logger->allowRelease();

		if (in_array($audit, [ Audit::CATALOG_STOCKS, Audit::CATALOG_PRICE, Audit::CATALOG_ARCHIVE ], true) && $this->historySaleAudit())
		{
			$logger->allowCheckExists([
				'=LEVEL' => [ Level::ERROR, Level::WARNING, Level::NOTICE ],
			]);
		}
		else
		{
			$logger->allowCheckExists();
		}

		return $logger;
	}

	private function historySaleAudit()
	{
		return (Config::getOption('catalog_submitter_history_sale', 'N') === 'Y');
	}
	
	private function tasks(Catalog\Endpoint\Driver $driver, $priority, $needPrepare, LoggerInterface $logger)
	{
		$limit = $driver->limit();
		$left = $limit;
		$offset = 0;
		$tasks = [];
		$assortment = [];
		$driverType = $driver->type();

		do
		{
			$loopTasks = $this->fetchTasks($driver, $priority, $needPrepare, $limit, $offset);
			$loopAssortment = $this->fetchAssortment(array_keys($loopTasks));
			$hasNext = (count($loopTasks) >= $limit);

			if ($logger instanceof Logger)
			{
				$logger->registerElements(Catalog\Glossary::ENTITY_SKU, array_column($loopTasks, 'SKU'));
			}

			if ($driverType !== Catalog\Glossary::ENDPOINT_ARCHIVE)
			{
				$loopTasks = $this->sliceUnknown($driver, $loopTasks, $loopAssortment, $logger);

				if ($driverType !== Catalog\Glossary::ENDPOINT_OFFER || !$this->processor->getSetup()->isOfferEnabled())
				{
					list($loopTasks, $loopAssortment) = $this->sliceMissing($driver, $loopTasks, $loopAssortment, $logger);
				}
			}

			if (count($loopTasks) > $left)
			{
				$loopTasks = array_slice($loopTasks, 0, $left, true);
				$loopAssortment = array_intersect_key($loopAssortment, $loopTasks);
			}

			$tasks += $loopTasks;
			$assortment += $loopAssortment;
			$left -= count($loopTasks);

			if (!$hasNext) { break; }

			$offset += $limit;
		}
		while ($left > 0);

		return [ $tasks, $assortment ];
	}

    private function fetchTasks(Catalog\Endpoint\Driver $driver, $priority, $needPrepare, $limit, $offset)
    {
        $result = [];

        $query = Storage\QueueTable::getList([
            'select' => array_keys(array_filter([
                'SKU' => true,
                'PAYLOAD' => true,
                'PREPARED' => $needPrepare,
            ])),
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                '=ENDPOINT' => $driver->type(),
                '=CAMPAIGN_ID' => $driver->campaignId(),
                '=STATUS' => Storage\QueueTable::STATUS_WAIT,
                '=PRIORITY' => $priority,
            ],
            'limit' => $limit,
	        'offset' => $offset,
        ]);

        while ($row = $query->fetch())
        {
            if (!$needPrepare)
            {
                $task = [ 'PAYLOAD' => $row['PAYLOAD'] ];
            }
            else if (is_array($row['PREPARED']))
            {
                $task = [ 'PAYLOAD' => $row['PREPARED'] ];
            }
            else
            {
                $task = [ 'RAW' => $row['PAYLOAD'] ];
            }

            $result[$row['SKU']] = $task;
        }

        return $result;
    }

	private function fetchAssortment(array $skus)
	{
		if (empty($skus)) { return []; }

		$result = [];

		$query = Storage\AssortmentTable::getList([
			'filter' => [
				'=CATALOG_ID' => $this->processor->getSetup()->getId(),
				'=SKU' => $skus,
			],
			'select' => [ 'SKU', 'STATUS', 'CATEGORY_ID', 'ELEMENT_ID' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['SKU']] = $row;
		}

		return $result;
	}

	private function sliceUnknown(Catalog\Endpoint\Driver $driver, array $tasks, array $assortment, LoggerInterface $logger)
	{
		$unknownTasks = array_diff_key($tasks, $assortment);
		$unknownSkus = array_keys($unknownTasks);

		$this->queueCommiter->missing($driver, $unknownSkus);

		foreach ($unknownSkus as $sku)
		{
			$logger->notice(self::getMessage('UNKNOWN_SKU', [
				'#SKU#' => $sku,
			]), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);
		}

		return array_diff_key($tasks, $unknownTasks);
	}

	private function sliceMissing(Catalog\Endpoint\Driver $driver, array $tasks, array $assortment, LoggerInterface $logger)
	{
		$missingAssortment = array_filter($assortment, static function (array $row) { return $row['STATUS'] === Storage\AssortmentTable::STATUS_MISSING; });

		$this->queueCommiter->missing($driver, array_keys($missingAssortment));

		foreach ($missingAssortment as $sku => $assortmentRow)
		{
			$logger->notice(self::getMessage('MISSING_SKU', [
				'#SKU#' => $sku,
			]), $assortmentRow['ELEMENT_ID'] > 0 ? [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_OFFER,
				'ENTITY_ID' => $assortmentRow['ELEMENT_ID'],
			] : [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);
		}

		return [
			array_diff_key($tasks, $missingAssortment),
			array_diff_key($assortment, $missingAssortment),
		];
	}
}