<?php
namespace Yandex\Market\Reference\Storage;

use Bitrix\Main;
use Yandex\Market\Reference\Common;

abstract class Collection extends Common\Collection
{
	/** @var Model[] */
	protected $collection = [];
	/** @var Model */
	protected $parent;
	protected $changed = false;

	public static function getClassName()
	{
		return '\\' . static::class;
	}

	/**
	 * Çàãğóæàåì êîëëåêöèè äëÿ ğîäèòåëüñêèõ ñóùíîñòåé
	 *
	 * @param Model[] $parents
	 * @param array   $filter
	 * @param string|callable $associationFlag
	 *
	 * @return static[]
	 */
	public static function loadBatch(array $parents, $filter, $associationFlag)
	{
		$result = static::makeBatchCollections($parents);

		if (!empty($result))
		{
			$items = static::queryItems($filter);

			static::applyBatchAssociationFlag($result, $items, $associationFlag);
		}

		return $result;
	}

	/**
	 * @param Model[] $parents
	 *
	 * @return static[]
	 */
	protected static function makeBatchCollections(array $parents)
	{
		$result = [];

		foreach ($parents as $parent)
		{
			$parentId = (string)$parent->getId();

			if ($parentId !== '')
			{
				$collection = new static();
				$collection->setParent($parent);

				$result[$parentId] = $collection;
			}
		}

		return $result;
	}

	/**
	 * @param Collection[] $collections
	 * @param Model[] $models
	 * @param string|callable $associationFlag
	 *
	 * @throws Main\ArgumentException
	 */
	protected static function applyBatchAssociationFlag(array $collections, array $models, $associationFlag)
	{
		foreach ($models as $model)
		{
			if (is_string($associationFlag))
			{
				$linkValue = $model->getField($associationFlag);

				if (!isset($collections[$linkValue])) { continue; }

				$collection = $collections[$linkValue];

				$model->setParentCollection($collection);
				$collection->addItem($model);
			}
			else if (is_callable($associationFlag))
			{
				/** @noinspection VariableFunctionsUsageInspection */
				call_user_func($associationFlag, $collections, $model);
			}
			else
			{
				throw new Main\ArgumentException('unknown associationFlag format');
			}
		}
	}

	/**
	 * Çàãğóæàåì êîëëåêöèş äëÿ ğîäèòåëüñêîé ñóùíîñòè
	 *
	 * @param Model $parent
	 * @param array $filter
	 *
	 * @return static
	 * @throws Main\SystemException
	 */
	public static function load(Model $parent, $filter)
	{
		$collection = $parent->getId() > 0 ? static::loadByFilter($filter) : new static();
		$collection->setParent($parent);

		return $collection;
	}

	/**
	 * Çàãğóæàåì êîëëåêöèş ïî ôèëüòğó
	 *
	 * @param array $filter
	 *
	 * @return static
	 *
	 * @throws Main\ArgumentException
	 * @throws Main\SystemException
	 */
	public static function loadByFilter($filter)
	{
		$collection = new static();

		foreach (static::queryItems($filter) as $model)
		{
			$model->setParentCollection($collection);
			$collection->addItem($model);
		}

		return $collection;
	}

	/**
	 * @param array|null $filter
	 *
	 * @return Model[]
	 * @throws Main\SystemException
	 */
	protected static function queryItems($filter)
	{
		$modelClassName = static::getItemReference();

		if (!isset($modelClassName)) { throw new Main\SystemException('reference item not defined'); }

		return $modelClassName::loadList($filter);
	}

	public function addItem(Common\Model $model)
	{
		parent::addItem($model);
		$this->changed = true;
	}

	public function isChanged()
	{
		if ($this->changed) { return true; }

		foreach ($this->collection as $model)
		{
			if ($model->isChanged())
			{
				return true;
			}
		}

		return false;
	}

	public function save()
	{
		foreach ($this->collection as $model)
		{
			$model->save();
		}
	}
}