<?php
namespace Yandex\Market\Logger\Reference;

use Yandex\Market\Result;
use Yandex\Market\Psr;
use Yandex\Market\Data\Type\CanonicalDateTime;
use Yandex\Market\Config;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Logger\Level;
use Bitrix\Main;

abstract class Logger extends Psr\Log\AbstractLogger
{
	protected $level = Level::WARNING;
	protected $canTouchRows = true;
	protected $additionalGroupKeys = [];
	private $allowBatch = false;
	private $allowCheckExists = false;
	private $checkExistsFilter;
	private $allowRelease = false;
	private $elements = [];
	private $queue = [];
	private $context = [];
	private $chunkSize;

	public function __construct()
	{
		$this->chunkSize = max(1, (int)Config::getOption('log_flush_chunk_size', 100));
	}

	public function __destruct()
	{
		$this->flush();
	}

	/** @return class-string<Storage\Table> */
	abstract public function getDataClass();

	public function setLevel($level)
	{
		$this->level = (string)$level;
	}

	public function log($level, $message, array $context = [])
	{
		if (!Level::isMatch($this->level, $level))
		{
			if (isset($context['ENTITY_TYPE'], $context['ENTITY_ID']))
			{
				$this->registerElement($context['ENTITY_TYPE'], $context['ENTITY_ID']);
			}

			return;
		}

		$parsedMessage = $this->parseMessage($message);
		$context = $this->extendContext($message, $context);
		$context += $this->context;

		$row = $this->createRow($level, $parsedMessage, $context);

		$this->queue($row);
	}

	protected function parseMessage($message)
	{
		/**
		 * @noinspection PhpConditionCheckedByNextConditionInspection
		 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
		 * @noinspection NotOptimalIfConditionsInspection
		 */
		if ($message instanceof \Exception || $message instanceof \Throwable || $message instanceof Main\Error)
		{
			$errorCode = $message->getCode();
			$result = $message->getMessage() . (!empty($errorCode) ? ' #' . $errorCode : '');
		}
		else if ($message instanceof Main\Result || $message instanceof Result\Base)
		{
			$result = implode(PHP_EOL, $message->getErrorMessages());
		}
		else if (!is_scalar($message))
		{
			$result = print_r($message, true);
		}
		else
		{
			$result = (string)$message;
		}

		return $result;
	}

	public function resetContext(array $context)
	{
		$this->context = $context;
	}

	public function getFullContext()
	{
		return $this->context;
	}

	public function getContext($name)
	{
		return isset($this->context[$name]) ? $this->context[$name] : null;
	}

	public function setContext($name, $key)
	{
		$this->context[$name] = $key;
	}

	public function releaseContext()
	{
		$this->context = [];
	}

	/**
	 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
	 * @noinspection PhpConditionCheckedByNextConditionInspection
	 */
	protected function extendContext($message, $context)
	{
		if (
			($message instanceof \Exception || $message instanceof \Throwable)
			&& $this->isTracingOn()
		)
		{
			$context['TRACE'] = Main\Diag\ExceptionHandlerFormatter::format($message);
		}

		return $context;
	}

	protected function isTracingOn()
	{
		return true;
	}

	private function createRow($level, $message, array $context = [])
	{
		$row = $this->getRowDefaults() + [
			'TIMESTAMP_X' => new CanonicalDateTime(),
			'LEVEL' => $level,
			'MESSAGE' => $message,
		];
		$row = $this->passContextFields($row, $context);

		return $row;
	}

	protected function getContextFields()
	{
		return [];
	}

	protected function getRowDefaults()
	{
		return [];
	}

	private function passContextFields(array $row, array $context)
	{
		foreach ($this->getContextFields() as $name)
		{
			if (array_key_exists($name, $context))
			{
				$row[$name] = (string)$context[$name];
				unset($context[$name]);
			}
			else if (!isset($row[$name]))
			{
				$row[$name] = '';
			}
		}

		$row['CONTEXT'] = !empty($context) ? $context : null;

		return $row;
	}

	public function allowBatch()
	{
		$this->allowBatch = true;
	}

	/** @noinspection PhpUnused */
	public function disallowBatch()
	{
		$this->allowBatch = false;
	}

	public function allowCheckExists(array $filter = null)
	{
		$this->allowCheckExists = true;
		$this->checkExistsFilter = $filter;
	}

	/** @noinspection PhpUnused */
	public function disallowCheckExists()
	{
		$this->allowCheckExists = false;
	}

	public function allowRelease()
	{
		$this->allowRelease = true;
	}

	/** @noinspection PhpUnused */
	public function disallowRelease()
	{
		$this->allowRelease = false;
	}

	public function firstElement()
	{
		foreach ($this->elements as $entityType => $entityIds)
		{
			if (empty($entityIds)) { continue; }

			return [
				'ENTITY_TYPE' => $entityType,
				'ENTITY_ID' => reset($entityIds),
			];
		}

		return null;
	}

	public function registerElements($entityType, array $entityIds)
	{
		if (empty($entityIds)) { return; }

		if (!isset($this->elements[$entityType]))
		{
			$this->elements[$entityType] = [];
		}

		array_push($this->elements[$entityType], ...$entityIds);
	}

	public function registerElement($entityType, $entityId)
	{
		if (!isset($this->elements[$entityType]))
		{
			$this->elements[$entityType] = [];
		}

		$this->elements[$entityType][] = $entityId;
	}

	public function flush()
	{
		$queue = $this->queue;
		$elements = $this->elements;
		$this->queue = [];
		$this->elements = [];

		$this->write($queue, $elements);
	}

	private function queue(array $row)
	{
		if (!$this->allowBatch)
		{
			$elements = $this->elements;
			$this->elements = [];

			$this->write([ $row ], $elements);
			return;
		}

		$this->queue[] = $row;
	}

	private function write(array $rows, array $knownElements = null)
	{
		if ($this->allowCheckExists)
		{
			list($newRows, $updateIds, $deleteIds) = $this->splitExists($rows, $knownElements);

			$this->tableAdd($newRows);
			$this->tableUpdate($updateIds);
			$this->tableDelete($deleteIds);
			return;
		}

		$this->tableAdd($rows);
	}

	private function splitExists(array $rows, array $knownElements = null)
	{
		$exists = $this->tableExists($rows, $knownElements);
		$found = [];
		$deleteIds = [];

		foreach ($rows as $key => $row)
		{
			$groupKey = $this->rowGroupKey($row);

			if (!isset($exists[$groupKey])) { continue; }

			$id = array_search((string)$row['MESSAGE'], $exists[$groupKey], true);

			if ($id === false) { continue; }

			$found[$id] = true;
			unset($rows[$key]);
		}

		if ($this->allowRelease)
		{
			foreach ($exists as $group)
			{
				foreach ($group as $id => $message)
				{
					if (isset($found[$id])) { continue; }

					$deleteIds[] = $id;
				}
			}
		}

		return [ $rows, array_keys($found), $deleteIds ];
	}

	private function tableExists(array $rows, array $knownElements = null)
	{
		$rows = array_filter($rows, static function(array $row) { return $row['LEVEL'] !== Level::DEBUG; });
		$elementFilter = $this->existsElementFilter($this->rowsEntityGroups($rows, $knownElements));

		if ($elementFilter === null) { return []; }

		$result = [];
		$filter = $this->existsCommonFilter($rows);

		if ($this->checkExistsFilter !== null)
		{
			$filter = array_diff_key($filter, $this->checkExistsFilter) + $this->checkExistsFilter;
		}

		$filter[] = $elementFilter;

		$dataClass = $this->getDataClass();
		$query = $dataClass::getList([
			'filter' => $filter,
			'select' => array_merge($this->additionalGroupKeys, [
				'ID',
				'ENTITY_TYPE',
				'ENTITY_ID',
				'LEVEL',
				'MESSAGE',
			]),
		]);

		while ($row = $query->fetch())
		{
			$groupKey = $this->rowGroupKey($row);

			if (!isset($result[$groupKey])) { $result[$groupKey] = []; }

			$result[$groupKey][$row['ID']] = (string)$row['MESSAGE'];
		}

		return $result;
	}

	private function rowGroupKey(array $row)
	{
		$groupKey = "{$row['ENTITY_TYPE']}:{$row['ENTITY_ID']}:{$row['LEVEL']}";

		foreach ($this->additionalGroupKeys as $fieldKey)
		{
			$groupKey .= ':' . (isset($row[$fieldKey]) ? (string)$row[$fieldKey] : '');
		}

		return $groupKey;
	}

	abstract protected function existsCommonFilter(array $rows);

	private function rowsEntityGroups(array $rows, array $knownElements = null)
	{
		$known = $this->allowRelease && $knownElements !== null ? $knownElements : [];
		$result = $known;

		foreach ($rows as $row)
		{
			if (!isset($row['ENTITY_ID']) || (string)$row['ENTITY_ID'] === '' || isset($known[$row['ENTITY_TYPE']])) { continue; }

			if (!isset($result[$row['ENTITY_TYPE']]))
			{
				$result[$row['ENTITY_TYPE']] = [];
			}

			$result[$row['ENTITY_TYPE']][] = (string)$row['ENTITY_ID'];
		}

		return $result;
	}

	private function existsElementFilter(array $entityGroups)
	{
		$partials = [];

		foreach ($entityGroups as $entityType => $entityIds)
		{
			$partials[] = [
				'=ENTITY_TYPE' => $entityType,
				'=ENTITY_ID' => $entityIds,
			];
		}

		if (empty($partials)) { return null; }

		if (count($partials) === 1)
		{
			return $partials[0];
		}

		return [ 'LOGIC' => 'OR' ] + $partials;
	}

	private function tableAdd(array $rows)
	{
		foreach (array_chunk($rows, $this->chunkSize) as $chunk)
		{
			$dataClass = $this->getDataClass();
			$dataClass::addBatch($chunk);
		}
	}

	private function tableUpdate(array $ids)
	{
		if (!$this->canTouchRows) { return; }

		foreach (array_chunk($ids, $this->chunkSize) as $chunk)
		{
			$dataClass = $this->getDataClass();
			$dataClass::updateBatch([
				'filter' => [ '=ID' => $chunk ],
			], [
				'TIMESTAMP_X' => new CanonicalDateTime(),
			]);
		}
	}

	private function tableDelete(array $ids)
	{
		foreach (array_chunk($ids, $this->chunkSize) as $chunk)
		{
			$dataClass = $this->getDataClass();
			$dataClass::deleteBatch([
				'filter' => [ '=ID' => $chunk ],
			]);
		}
	}
}