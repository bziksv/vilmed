<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Api;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Utils\ArrayHelper;

class PrepareCategory
{
	use Concerns\HasMessage;

    private $processor;
    private $queueCommiter;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
        $this->queueCommiter = new QueueCommiter($processor->getSetup()->getId());
    }

    public function __invoke(Data\Run\Waterfall $waterfall, Catalog\Endpoint\Driver $driver, array $queue, array $assortment, Data\Run\Offset $offset, LoggerInterface $logger)
    {
        if (!($driver instanceof Catalog\Endpoint\DriverWithPrepareCategory))
        {
	        $waterfall->next($driver, $queue, $assortment, $offset, $logger);
            return;
        }

        $preparedQueue = [];
        $waitingBag = ArrayHelper::column($queue, 'RAW');
        $waitingAssortment = array_intersect_key($assortment, $waitingBag);

	    $this->failMissingAssortment($driver, $waitingBag, $assortment);

        foreach ($this->categoriesSkus($waitingAssortment) as $categoryId => $skus)
        {
			$categoryPayload = array_intersect_key($waitingBag, array_flip($skus));

	        $preparedQueue += $this->processCategory($driver, $categoryId, $categoryPayload, $logger);

            if ($this->processor->isExpired())
            {
                $this->insertPrepared($driver, $preparedQueue);
                $offset->interrupt();
                return;
            }
        }

        $queue = $preparedQueue + array_diff_key($queue, $waitingBag);

	    $waterfall->next($driver, $queue, $assortment, $offset, $logger);
    }

	private function failMissingAssortment(Catalog\Endpoint\Driver $driver, array $waitingBag, array $assortment)
	{
		$this->queueCommiter->error($driver, array_keys(array_diff_key($waitingBag, $assortment)));
	}

    private function categoriesSkus(array $assortment)
    {
        if (empty($assortment)) { return []; }

        $result = [];

        foreach ($assortment as $sku => $row)
        {
            $categoryId = (int)$row['CATEGORY_ID'];

            if (!isset($result[$categoryId])) { $result[$categoryId] = []; }

            $result[$categoryId][] = $sku;
        }

        return $result;
    }

    private function processCategory(Catalog\Endpoint\DriverWithPrepareCategory $driver, $categoryId, array $payloadBag, LoggerInterface $logger)
    {
        try
        {
            $auth = $this->processor->getSetup()->getBusiness()->getOptions()->getApiAuth();
            $prepared = $driver->prepareCategory($categoryId, $payloadBag, $auth, $logger);

            list($payloadBag, $error) = $this->parsePrepared(array_keys($payloadBag), $prepared, $logger);

            $this->queueCommiter->error($driver, $error);

            return $payloadBag;
        }
        catch (Api\Exception\BadRequestException $exception)
        {
			$skus = array_keys($payloadBag);

	        $this->queueCommiter->error($driver, $skus);

			foreach ($skus as $sku)
			{
				$logger->error($exception, [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
			}

            return [];
        }
    }

    private function parsePrepared(array $skus, array $prepareResults, LoggerInterface $logger)
    {
        $bag = [];
        $error = [];

        foreach ($skus as $sku)
        {
            if (!isset($prepareResults[$sku]))
            {
                $error[] = $sku;
	            $logger->error(self::getMessage('NOT_PREPARED'), [
		            'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
		            'ENTITY_ID' => $sku,
	            ]);
                continue;
            }

            /** @var Result\Base $prepareResult */
            $prepareResult = $prepareResults[$sku];

            if (!$prepareResult->isSuccess())
            {
	            $error[] = $sku;
				$logger->error($prepareResult, [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
                continue;
            }

			if ($prepareResult->hasWarnings())
			{
				$message = implode(PHP_EOL, $prepareResult->getWarningMessages());

				$logger->warning($message, [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
			}

            $bag[$sku] = [
                'PAYLOAD' => $prepareResult->getData(),
            ];
        }

        return [ $bag, $error ];
    }

    private function insertPrepared(Catalog\Endpoint\Driver $driver, array $preparedQueue)
    {
        if (empty($preparedQueue)) { return; }

        $rows = [];
        $catalogId = $this->processor->getSetup()->getId();

        foreach ($preparedQueue as $sku => $task)
        {
            $rows[] = [
                'CATALOG_ID' => $catalogId,
                'SKU' => $sku,
                'ENDPOINT' => $driver->type(),
                'CAMPAIGN_ID' => $driver->campaignId(),
                'PREPARED' => $task['PAYLOAD'],
            ];
        }

        Storage\QueueTable::addBatch($rows, true);
    }
}