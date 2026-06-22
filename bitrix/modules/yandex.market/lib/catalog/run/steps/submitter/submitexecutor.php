<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Api;
use Yandex\Market\Catalog;
use Yandex\Market\Data;
use Yandex\Market\Data\Run\PauseException;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Utils\ArrayHelper;

class SubmitExecutor
{
	use Concerns\HasMessage;

	const REPEAT_LIMIT = 2;
	const REPEAT_TIMEOUT = 60;

    private $processor;
    private $queueCommiter;
    private $assortmentCommiter;
    private $placementCommiter;
	private $stepName;

    public function __construct(Catalog\Run\Processor $processor, $stepName)
    {
        $this->processor = $processor;
        $this->queueCommiter = new QueueCommiter($processor->getSetup()->getId());
        $this->assortmentCommiter = new AssortmentCommiter($processor->getSetup()->getId());
        $this->placementCommiter = new PlacementCommiter($processor->getSetup()->getId());
		$this->stepName = $stepName;
    }

    public function __invoke(Data\Run\Waterfall $waterfall, Catalog\Endpoint\Driver $driver, array $queue, array $assortment, Data\Run\Offset $offset, LoggerInterface $logger)
    {
        try
        {
	        if (empty($queue)) { return; }

			$priority = $offset->get('priority');
			$repeatCount = floor($priority / 100);

            $auth = $this->processor->getSetup()->getBusiness()->getOptions()->getApiAuth();
            $bagResult = $driver->submit(ArrayHelper::column($queue, 'PAYLOAD'), $auth, $logger);

            list($success, $error, $repeat, $assortmentChanges, $placementChanges) = $this->parseSubmitted(array_keys($queue), $bagResult, $logger);

			if ($repeatCount >= self::REPEAT_LIMIT && !empty($repeat))
			{
				array_push($error, ...$repeat);
				$repeat = [];
			}

            $this->queueCommiter->success($driver, $success);
            $this->queueCommiter->repeat($driver, $repeat, $priority);
            $this->queueCommiter->error($driver, $error);
            $this->assortmentCommiter->write($assortmentChanges, $assortment, $priority);
            $this->placementCommiter->write($placementChanges, $assortment, $driver->campaignId());

			if ($repeatCount > 0 && !empty($repeat))
			{
				throw new PauseException($this->stepName, (string)$offset, self::REPEAT_TIMEOUT);
			}

	        if ($this->processor->isExpired())
	        {
		        $offset->interrupt();
		        return;
	        }

	        $waterfall->next($driver, $queue, $assortment, $offset, $logger);
        }
		catch (Api\Exception\LockedException $exception)
		{
			$this->queueCommiter->errorByMethod($driver);

			foreach ($queue as $sku => $dummy)
			{
				$logger->error($exception, [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
			}
		}
		catch (Api\Exception\BadRequestException $exception)
		{
			$skus = array_keys($queue);

			$this->queueCommiter->error($driver, $skus);

			foreach ($skus as $sku)
			{
				$logger->error($exception, [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
			}
		}
    }

    private function parseSubmitted(array $skus, array $bagResult, LoggerInterface $logger)
    {
        $success = [];
        $error = [];
        $repeat = [];
        $assortment = [];
        $placement = [];

        foreach ($skus as $sku)
        {
            if (!isset($bagResult[$sku]))
            {
                $error[] = $sku;
				$logger->error(self::getMessage('NOT_SUBMITTED'), [
					'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
					'ENTITY_ID' => $sku,
				]);
                continue;
            }

            /** @var Result\Base $taskResult */
            $taskResult = $bagResult[$sku];
	        $data = $taskResult->getData();

            if (!$taskResult->isSuccess())
            {
				if (!empty($data['REPEAT']))
				{
					$repeat[] = $sku;
				}
				else
				{
					$error[] = $sku;
				}

	            $logger->error($taskResult, [
		            'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
		            'ENTITY_ID' => $sku,
	            ]);
                continue;
            }

            if (!empty($data['ASSORTMENT']))
            {
                $assortment[$sku] = $data['ASSORTMENT'];
            }

            if (!empty($data['PLACEMENT']))
            {
                $placement[$sku] = $data['PLACEMENT'];
            }

            $success[] = $sku;
        }

        return [$success, $error, $repeat, $assortment, $placement];
    }
}