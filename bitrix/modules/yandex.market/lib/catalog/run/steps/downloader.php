<?php
namespace Yandex\Market\Catalog\Run\Steps;

use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data;
use Yandex\Market\Catalog;
use Yandex\Market\Result;

class Downloader extends Data\Run\StepSkeleton
{
    protected $processor;

    public function __construct(Catalog\Run\Processor $processor)
    {
        $this->processor = $processor;
    }

    public function getName()
    {
        return 'downloader';
    }

    public function run($action, $offset = null)
    {
        $result = new Result\Step();
        $offsetObject = new Data\Run\Offset($offset);

	    (new Data\Run\Waterfall())
		    ->add(new Transport\HttpCatcher($this->getName(), $this->processor->makeLogger()))
		    ->add([$this, 'iterateSteps'])
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

	public function iterateSteps(Data\Run\Waterfall $waterfall, $action, Data\Run\Offset $offset)
	{
		foreach ($this->steps($action) as $step)
		{
			if (!$offset->tick('step')) { continue; }

			$step->run($offset);

			if ($offset->interrupted()) { break; }
		}

		$waterfall->next();
	}

    private function steps($action)
    {
        if ($action === Catalog\Run\Processor::ACTION_FULL)
        {
            return [
                new Downloader\PublishedReloader($this->processor),
                new Downloader\UnknownActualizer($this->processor),
            ];
        }

        return [
            new Downloader\UnknownActualizer($this->processor),
        ];
    }

    private function readyCount()
    {
        return Storage\AssortmentTable::getCount([
            '=CATALOG_ID' => $this->processor->getSetup()->getId(),
            '>=TIMESTAMP_X' => $this->processor->getParameter('initTime'),
            '!=STATUS' => Storage\AssortmentTable::STATUS_UNKNOWN,
        ]);
    }
}

