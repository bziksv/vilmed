<?php
namespace Yandex\Market\Catalog\Run\Steps;

use Yandex\Market\Data;
use Yandex\Market\Result;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;

class Submitter extends Data\Run\StepSkeleton
    implements Data\Run\StepClearable
{
	protected $processor;

	public function __construct(Catalog\Run\Processor $processor)
	{
		$this->processor = $processor;
	}

	public function getName()
	{
		return 'submitter';
	}

    public function clear()
    {
        $catalogId = $this->processor->getSetup()->getId();

        Catalog\Run\Storage\QueueTable::deleteBatch([
            'filter' => [ '=CATALOG_ID' => $catalogId ],
        ]);
    }

	public function run($action, $offset = null)
	{
		$result = new Result\Step();
		$offsetObject = new Data\Run\Offset($offset);

		(new Data\Run\Waterfall())
			->add(new Submitter\TasksFetcher($this->processor))
			->add(new Transport\HttpCatcher($this->getName()))
			->add(new Submitter\PrepareCategory($this->processor))
			->add(new Submitter\SubmitExecutor($this->processor, $this->getName()))
			->run($offsetObject);

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

	protected function readyCount()
	{
        return Storage\QueueTable::getCount([
            '=CATALOG_ID' => $this->processor->getSetup()->getId(),
            '=STATUS' => [ Storage\QueueTable::STATUS_SUCCESS, Storage\QueueTable::STATUS_ERROR ],
            '>=TIMESTAMP_X' => $this->processor->getParameter('initTime'),
        ]);
	}
}

