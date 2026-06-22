<?php
namespace Yandex\Market\Reference\Storage;

use Bitrix\Main;
use Yandex\Market;

abstract class Model extends Market\Reference\Common\Model
{
	use Market\Reference\Concerns\HasMessage;

	protected $originalFields = [];

	/** @return static|null */
	public static function loadOne(array $parameters = [])
	{
		$models = static::loadList($parameters + [ 'limit' => 1 ]);

		if (empty($models)) { return null; }

		return reset($models);
	}

	/** @return static[] */
	public static function loadList(array $parameters = [])
	{
		$result = [];
		$tableClass = static::getDataClass();
		$distinctField = null;
		$distinctMap = null;

		if (isset($parameters['distinct']))
		{
			$distinctMap = [];

			if ($parameters['distinct'] === true)
			{
				$distinctField = $tableClass::getEntity()->getPrimary();
			}
			else
			{
				$distinctField = $parameters['distinct'];
			}

			unset($parameters['distinct']);
		}

		$query = $tableClass::getList($parameters);

		while ($itemData = $query->fetch())
		{
			if ($distinctField !== null && isset($itemData[$distinctField]))
			{
				$itemDistinctValue = $itemData[$distinctField];

				if (isset($distinctMap[$itemDistinctValue])) { continue; }

                $distinctMap[$itemDistinctValue] = true;
			}

			$result[] = new static($itemData);
		}

		return $result;
	}

	/** @return static */
	public static function loadById($id)
	{
		$tableClass = static::getDataClass();
        $itemData = $tableClass::getById($id)->fetch();

		if (!$itemData)
		{
			throw new Main\ObjectNotFoundException(self::getMessage('LOAD_NOT_FOUND'));
		}

		return new static($itemData);
	}

	/** @return class-string<Table> */
	public static function getDataClass()
	{
		throw new Main\SystemException('not implemented');
	}

	public function __construct(array $fields = [])
	{
		parent::__construct($fields);
		$this->originalFields = $fields;
	}

	/**
	 * @template T
	 * @param string $fieldKey
	 * @param class-string<T> $className
	 * @return T
	 */
	protected function requireModel($fieldKey, $className)
	{
		$model = $this->getModel($fieldKey, $className);

		if ($model === null)
		{
			throw new Main\ObjectNotFoundException(self::getMessage('MODEL_NOT_FOUND', [
				'#FIELD#' => $fieldKey,
			]));
		}

		return $model;
	}

	protected function buildModel($fieldKey, $className)
	{
		/** @var class-string<Model> $className */
		Market\Reference\Assert::isSubclassOf($className, self::class);

		if ($this->hasField($fieldKey))
		{
			$result = $className::initialize((array)$this->getField($fieldKey));
			$result->setParent($this);

			return $result;
		}

		$queryParams = $this->getChildCollectionQueryParameters($fieldKey);

		if ($queryParams !== null)
		{
			$queryParams['limit'] = 1;
			$models = $className::loadList($queryParams);

			if (empty($models)) { return null; }

			$result = reset($models);
			$result->setParent($this);

			return $result;
		}

		return null;
	}

	protected function buildCollection($fieldKey, $className)
	{
		Market\Reference\Assert::isSubclassOf($className, Collection::class);

		if ($this->hasField($fieldKey))
		{
			$dataList = (array)$this->getField($fieldKey);
			$result = $className::initialize($dataList, $this);
		}
		else if ($this->getId() > 0 && $this->hasSiblings() && $this->supportsBatchCollectionLoading($fieldKey))
		{
			$result = $this->batchChildCollection($className, $fieldKey);
		}
		else
		{
			$result = $this->queryChildCollection($className, $fieldKey);

			if ($result === null)
			{
				$result = new $className;
				$result->setParent($this);

				return $result;
			}
		}

		return $result;
	}

	/**
	 * Загрузка дочерней коллекции из базы данных
	 *
	 * @param class-string<Collection> $collectionClassName
	 * @param string $fieldKey
	 *
	 * @return Collection
	 */
	protected function queryChildCollection($collectionClassName, $fieldKey)
	{
		$queryParams = $this->getChildCollectionQueryParameters($fieldKey);

		if ($queryParams === null) { return null; }

		return $collectionClassName::load($this, $queryParams);
	}

	/**
	 * Групповая загрузка дочерних коллекций из базы данных
	 *
	 * @param Collection $collectionClassName
	 * @param string $fieldKey
	 *
	 * @return Collection
	 * @throws Main\ObjectNotFoundException
	 */
	protected function batchChildCollection($collectionClassName, $fieldKey)
	{
		$siblingsMap = $this->getBatchSiblingsMap($fieldKey);
		$queryParams = $this->getChildCollectionBatchParameters($fieldKey, $siblingsMap);
		$associationFlag = $this->getChildCollectionAssociationFlag($fieldKey);
		$siblingCollections = $collectionClassName::loadBatch($siblingsMap, $queryParams, $associationFlag);
		$result = null;

		foreach ($siblingsMap as $siblingId => $sibling)
		{
			if (!isset($siblingCollections[$siblingId])) { throw new Main\ObjectNotFoundException('batch child collection not loaded'); }

			$collection = $siblingCollections[$siblingId];

			if ($sibling === $this)
			{
				$result = $collection;
			}
			else
			{
				$sibling->passChildCollection($fieldKey, $collection);
			}
		}

		if ($result === null)
		{
			throw new Main\ObjectNotFoundException('batch child collection not loaded for self');
		}

		return $result;
	}

	/**
	 * Переопределить дочернюю коллекцию (используется для групповой загрузки)
	 *
	 * @param string $fieldKey
	 * @param Collection $collection
	 *
	 * @throws Main\SystemException
	 */
	protected function passChildCollection($fieldKey, Collection $collection)
	{
		if (isset($this->childCollection[$fieldKey]))
		{
			throw new Main\SystemException('child collection already loaded');
		}

		$this->childCollection[$fieldKey] = $collection;
	}

	/**
	 * Имеет ли соседние элементы для групповой загрузки
	 *
	 * @return bool
	 */
	protected function hasSiblings()
	{
		$collection = $this->getParentCollection();

		return $collection !== null && count($collection) > 1;
	}

	/**
	 * Соседние элементы для групповой загрузки дочерних элементов
	 *
	 * @param string $fieldKey
	 *
	 * @return array<int|string, Model>
	 */
	protected function getBatchSiblingsMap($fieldKey)
	{
		$result = [];

		/** @var Model $sibling */
		foreach ($this->getParentCollection() as $sibling)
		{
			if (!$sibling->supportsBatchCollectionLoading($fieldKey)) { continue; }

			$siblingId = $sibling->getId();

			if ((string)$siblingId !== '')
			{
				$result[$siblingId] = $sibling;
			}
		}

		return $result;
	}

	/**
	 * Поддерживает загрузку дочерней коллекции группой
	 *
	 * @param string $fieldKey
	 *
	 * @return bool
	 */
	protected function supportsBatchCollectionLoading($fieldKey)
	{
		return $this->getChildCollectionAssociationFlag($fieldKey) !== null;
	}

	/**
	 * Правило распределения моделей по коллекциям при групповой загрузке
	 *
	 * @param string $fieldKey
	 *
	 * @return string|callable|null
	 */
	protected function getChildCollectionAssociationFlag($fieldKey)
	{
		$tableClass = static::getDataClass();
		$reference = $tableClass::getReference();
		$result = null;

		if (isset($reference[$fieldKey]['LINK_FIELD']) && is_string($reference[$fieldKey]['LINK_FIELD']))
		{
			$result = $reference[$fieldKey]['LINK_FIELD'];
		}

		return $result;
	}

	protected function getChildCollectionBatchParameters($fieldKey, $siblingsMap)
	{
		$ids = array_keys($siblingsMap);

		return $this->makeCollectionQueryParameters($fieldKey, $ids);
	}

	protected function getChildCollectionQueryParameters($fieldKey)
	{
		$id = $this->getId();

		if ((int)$id === 0) { return null; }

		return $this->makeCollectionQueryParameters($fieldKey, $id);
	}

	protected function makeCollectionQueryParameters($fieldKey, $ids)
	{
		$tableClass = static::getDataClass();
		$reference = $tableClass::getReference($ids);

		if (!isset($reference[$fieldKey]['LINK'])) { throw new Main\SystemException('child reference not found'); }

		$queryParams = [
			'filter' => $tableClass::makeReferenceLinkFilter($reference[$fieldKey]['LINK']),
		];

		if (isset($reference[$fieldKey]['ORDER']))
		{
			$queryParams['order'] = $reference[$fieldKey]['ORDER'];
		}

		return $queryParams;
	}

	public function setField($name, $value)
	{
		parent::setField($name, $value);

		// force reload children
		if (isset($this->childCollection[$name]))
		{
			unset($this->childCollection[$name]);
		}

		if (isset($this->childModel[$name]))
		{
			unset($this->childModel[$name]);
		}
	}

	public function isNew()
	{
		return empty($this->originalFields['ID']);
	}

	public function isChanged()
	{
		$dataClass = static::getDataClass();
		$fields = array_intersect_key($this->fields, $dataClass::getEntity()->getScalarFields() + $dataClass::getReference());
		$changed = array_diff_assoc($fields, $this->originalFields);

		return !empty($changed);
	}

	public function save()
	{
		$dataClass = static::getDataClass();
		$fields = array_intersect_key($this->fields, $dataClass::getEntity()->getScalarFields() + $dataClass::getReference());

		if (empty($this->originalFields['ID']))
		{
			$addResult = $dataClass::add($fields);

			Market\Result\Facade::handleException($addResult);

			$this->setField('ID', $addResult->getId());
		}
		else
		{
			$changed = array_diff_assoc($fields, $this->originalFields);

			if (empty($changed)) { return; }

			$updateResult = $dataClass::update($this->originalFields['ID'], $changed);

			Market\Result\Facade::handleException($updateResult);
		}

		$this->originalFields = $this->fields;
	}

	public function delete()
	{
		$dataClass = static::getDataClass();
		$id = $this->getField('ID');

		if (empty($id)) { return; }

		$deleteResult = $dataClass::delete($id);

		Market\Result\Facade::handleException($deleteResult);
	}
}