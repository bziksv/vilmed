<?php
namespace Yandex\Market\SalesBoost\Run\Steps;

use Yandex\Market\Api;
use Yandex\Market\Data;
use Yandex\Market\Glossary;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Result;
use Yandex\Market\SalesBoost;
use Yandex\Market\Trading;
use Yandex\Market\Logger;

class Submitter extends Data\Run\StepSkeleton
{
	const TIMEOUT_MINUTES_POW = 2;
	const TIMEOUT_MINUTES_LIMIT = 30;

	const SERVER_REPEAT = 100;
	const METHOD_REPEAT = 10;
	const CLIENT_REPEAT = 5;

	protected $processor;

	public function __construct(SalesBoost\Run\Processor $processor)
	{
		$this->processor = $processor;
	}

	public function getName()
	{
		return 'submitter';
	}

	public function run($action, $offset = null)
	{
		$result = new Result\Step();
		$offsetObject = new Data\Run\Offset($offset);

		(new Data\Run\Waterfall())
			->add([$this, 'iterateBusiness'])
			->add([$this, 'iterateElements'])
			->add([$this, 'submit'])
			->add([$this, 'commit'])
			->run($offsetObject);

		if ($offsetObject->interrupted())
		{
			$result->setOffset((string)$offsetObject);
			$result->setTotal(1);

			if ($this->processor->parameter('progressCount') === true)
			{
				$result->setReadyCount($this->readyCount());
			}
		}

		return $result;
	}

	protected function readyCount()
	{
		return $this->submittedCount() + $this->deletedCount();
	}

	protected function submittedCount()
	{
		return SalesBoost\Run\Storage\SubmitterTable::getCount([
			'>=TIMESTAMP_X' => $this->processor->parameter('initTimeUTC'),
			'=STATUS' => [
				SalesBoost\Run\Storage\SubmitterTable::STATUS_ACTIVE,
				SalesBoost\Run\Storage\SubmitterTable::STATUS_ERROR,
			],
		]);
	}

	protected function deletedCount()
	{
		return SalesBoost\Run\Storage\CollectorTable::getCount([
			'>=TIMESTAMP_X' => $this->processor->parameter('initTimeUTC'),
			'=STATUS' => SalesBoost\Run\Storage\CollectorTable::STATUS_DELETE,
			'SUBMITTER.ELEMENT_ID' => false,
		]);
	}

	public function iterateBusiness(Data\Run\Waterfall $waterfall, Data\Run\Offset $offset)
	{
		do
		{
			$previous = $offset->get('business');
			$filter = (
				($previous !== null ? [ '>BUSINESS_ID' => $previous ] : [])
				+ [ '>=TIMESTAMP_X' => $this->processor->parameter('initTimeUTC') ]
			);

			$row = SalesBoost\Run\Storage\SubmitterTable::getRow([
				'select' => [ 'BUSINESS_ID' ],
				'filter' => $filter,
				'order' => [ 'BUSINESS_ID' => 'ASC' ],
			]);

			if ($row === null) { break; }

			$business = Trading\Business\Model::loadById($row['BUSINESS_ID']);

			$waterfall->next($business, $offset);

			if ($offset->interrupted()) { break; }

			$offset->set('business', $row['BUSINESS_ID']);
		}
		while (true);
	}

	public function iterateElements(Data\Run\Waterfall $waterfall, Trading\Business\Model $business, Data\Run\Offset $offset)
	{
		do
		{
			$previous = $offset->get('element');
			$elements = $this->fetchElements($business, $previous);

			if (empty($elements)) { break; }

			$waterfall->next($business, $elements, $offset);

			if ($offset->interrupted()) { break; }

			$lastElement = end($elements);
			$offset->set('element', $lastElement['ELEMENT_ID']);

			if ($this->processor->isExpired())
			{
				$offset->interrupt();
				break;
			}
		}
		while (true);
	}

	protected function fetchElements(Trading\Business\Model $business, $offset = null)
	{
		$result = [];

		$filter = (
			[ '=BUSINESS_ID' => $business->getId() ]
			+ ($offset !== null ? [ '>ELEMENT_ID' => $offset ] : [])
			+ [
				'=STATUS' => [
					SalesBoost\Run\Storage\SubmitterTable::STATUS_READY,
					SalesBoost\Run\Storage\SubmitterTable::STATUS_DELETE,
				],
				'>=TIMESTAMP_X' => $this->processor->parameter('initTimeUTC'),
			]
		);

		$query = SalesBoost\Run\Storage\SubmitterTable::getList([
			'select' => [ 'SKU', 'BOOST_ID', 'ELEMENT_ID', 'STATUS', 'BID' ],
			'filter' => $filter,
			'order' => [ 'ELEMENT_ID' => 'ASC' ],
			'limit' => 500,
		]);

		while ($row = $query->fetch())
		{
			$result[$row['ELEMENT_ID']] = $row;
		}

		return $result;
	}

	public function submit(Data\Run\Waterfall $waterfall, Trading\Business\Model $business, array $elements, Data\Run\Offset $offset)
	{
		try
		{
			$this->submitQuery($business, $this->compileBids($elements));

			$waterfall->next($business, $elements, true);
		}
		catch (Api\Exception\MethodFailureException $exception)
		{
			if ($this->moveRepeat($offset, self::METHOD_REPEAT)) { return; }

			throw $exception;
		}
		catch (Api\Exception\ServerErrorException $exception)
		{
			if ($this->moveRepeat($offset, self::SERVER_REPEAT)) { return; }

			throw $exception;
		}
		catch (Api\Exception\ClientException $exception)
		{
			if ($this->moveRepeat($offset, self::CLIENT_REPEAT)) { return; }

			throw $exception;
		}
		catch (Api\Exception\BadRequestException $exception)
		{
			$this->makeLogger($business)->error($exception);
			$waterfall->next($business, $elements, false);
		}
	}

	protected function compileBids(array $elements)
	{
		$result = [];

		foreach ($elements as $element)
		{
			$result[] = [
				'sku' => (string)$element['SKU'],
				'bid' => (
					$element['STATUS'] === SalesBoost\Run\Storage\SubmitterTable::STATUS_READY
						? max(0, (int)$element['BID'])
						: 0
				)
			];
		}

		return $result;
	}

	protected function submitQuery(Trading\Business\Model $business, array $bids)
	{
		$request = new Api\Business\Bids\Request($business->getId(), $business->getOptions()->getApiAuth(), $this->makeLogger($business));
		$request->setBids($bids);

		$request->execute();
	}

	protected function moveRepeat(Data\Run\Offset $offset, $limit)
	{
		global $pPERIOD;

		$repeated = (int)$offset->get('repeat');

		if ($repeated >= $limit) { return false; }

		$pPERIOD = min(self::TIMEOUT_MINUTES_POW ** $repeated, self::TIMEOUT_MINUTES_LIMIT) * 60;

		$offset->set('repeat', $repeated + 1);
		$offset->interrupt();

		return true;
	}

	public function commit(Data\Run\Waterfall $waterfall, Trading\Business\Model $business, array $elements, $isSuccess = true)
	{
		list($active, $delete) = $this->splitChanges($elements);

		if ($isSuccess)
		{
			$this->commitActive($business, $active);
			$this->commitDelete($business, $delete);
		}
		else
		{
			$this->commitActive($business, $active, false);
		}

		$waterfall->next($business, $elements);
	}

	protected function splitChanges(array $elements)
	{
		$active = [];
		$delete = [];

		foreach ($elements as $element)
		{
			if ($element['STATUS'] === SalesBoost\Run\Storage\SubmitterTable::STATUS_READY)
			{
				$active[] = $element['SKU'];
			}
			else
			{
				$delete[] = $element['SKU'];
			}
		}

		return [$active, $delete];
	}

	protected function commitActive(Trading\Business\Model $business, array $skus, $isSuccess = true)
	{
		if (empty($skus)) { return; }

		SalesBoost\Run\Storage\SubmitterTable::updateBatch([
			'filter' => [
				'=BUSINESS_ID' => $business->getId(),
				'=SKU' => $skus,
			],
		], [
			'STATUS' => $isSuccess
				? SalesBoost\Run\Storage\SubmitterTable::STATUS_ACTIVE
				: SalesBoost\Run\Storage\SubmitterTable::STATUS_ERROR,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}

	protected function commitDelete(Trading\Business\Model $business, array $skus)
	{
		if (empty($skus)) { return; }

		SalesBoost\Run\Storage\SubmitterTable::deleteBatch([
			'filter' => [
				'=BUSINESS_ID' => $business->getId(),
				'=SKU' => $skus,
			],
		]);
	}

	protected function makeLogger(Trading\Business\Model $business)
	{
		$logger = new Logger\Trading\Logger(Glossary::SERVICE_SALES_BOOST, 0);
		$logger->setContext('BUSINESS_ID', $business->getId());
		$logger->setContext('AUDIT', Logger\Trading\Audit::SALES_BOOST);

		return $logger;
	}
}

