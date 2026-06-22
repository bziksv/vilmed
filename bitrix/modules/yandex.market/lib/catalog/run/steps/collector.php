<?php
namespace Yandex\Market\Catalog\Run\Steps;

use Yandex\Market\Data;
use Yandex\Market\Glossary;
use Yandex\Market\Result;
use Yandex\Market\Catalog;

class Collector extends Data\Run\StepSkeleton
    implements Data\Run\StepClearable
{
	protected $processor;

	public function __construct(Catalog\Run\Processor $processor)
	{
		$this->processor = $processor;
	}

	public function getName()
	{
		return 'collector';
	}

    public function clear()
    {
        $filter = [
            'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
            ],
        ];

        Catalog\Run\Storage\OfferTable::deleteBatch($filter);
        Catalog\Run\Storage\HashTable::deleteBatch($filter);
    }

	public function validateAction($action)
	{
		if ($action === Data\Run\Processor::ACTION_CHANGE)
		{
			return ($this->changesFilter() !== null);
		}

		return true;
	}

	public function run($action, $offset = null)
	{
		$result = new Result\Step();
		$state = $this->createState($action);
		$offsetObject = new Data\Run\Offset($offset);

		(new Data\Run\Waterfall())
			->add(new Transport\HttpCatcher($this->getName(), $this->processor->makeLogger()))
			->add(new Collector\ElementFetcher($this->processor))
			->add(new Collector\OffersBuilder())
			->add(new Collector\PayloadCompiler())
			->add(new Collector\TasksBuilder())
			->add(new Collector\QueueScheduler())
			->run($state, $offsetObject);

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
		return Catalog\Run\Storage\OfferTable::getCount([
            '=CATALOG_ID' => $this->processor->getSetup()->getId(),
			'>=TIMESTAMP_X' => $this->processor->getParameter('initTime'),
		]);
	}

	public function after($action)
	{
		if ($action === Data\Run\Processor::ACTION_CHANGE)
		{
			$this->markDeleted($this->changesFilter());
		}
		else
		{
			$this->markDeleted();
		}
	}

	public function finalize($action)
	{
		if ($action === Data\Run\Processor::ACTION_CHANGE)
		{
			$this->clearDeleted($this->changesFilter());
		}
		else
		{
			$this->clearDeleted();
		}
	}

	protected function createState($action)
	{
		$state = new Collector\State();
		$state->runAction = $action;
		$state->initTime = $this->processor->getParameter('initTime');
		$state->catalog = $this->processor->getSetup();
		$state->context = $state->catalog->getContext();

		if ($action === Data\Run\Processor::ACTION_CHANGE)
		{
			$state->changes = $this->processor->getParameter('changes');
		}

		return $state;
	}

	protected function changesFilter()
	{
        $changes = $this->processor->getParameter('changes');

        if (!empty($changes[Glossary::ENTITY_CURRENCY])) { return []; }

        if (empty($changes[Glossary::ENTITY_OFFER])) { return null; }

        $ids = array_filter((array)$changes[Glossary::ENTITY_OFFER]);

        if (empty($ids)) { return null; }

        return [
            [
                'LOGIC' => 'OR',
                [ '=ELEMENT_ID' => $ids ],
                [ '=PARENT_ID' => $ids ]
            ],
        ];
	}

	protected function markDeleted(array $filter = [])
	{
		Catalog\Run\Storage\OfferTable::updateBatch([
			'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                $filter,
                '<TIMESTAMP_X' => $this->processor->getParameter('initTime'),
            ],
		], [
			'STATUS' => Catalog\Run\Storage\OfferTable::STATUS_DELETE,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}

	protected function clearDeleted(array $filter = [])
	{
		Catalog\Run\Storage\OfferTable::deleteBatch([
			'filter' => [
                '=CATALOG_ID' => $this->processor->getSetup()->getId(),
                $filter,
                '=STATUS' => Catalog\Run\Storage\OfferTable::STATUS_DELETE,
            ],
		]);
	}
}

