<?php
namespace Yandex\Market\Reference\Common;

use Bitrix\Main;
use Yandex\Market;

/**
 * @property Model[] $collection
*/
abstract class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
	use Market\Reference\Concerns\HasCollection;

	/** @var Model|null */
	protected $parent;

	/** @return class-string<Model> */
	public static function getItemReference()
	{
		throw new Main\NotImplementedException();
	}

	public static function initialize($dataList, Model $parent = null)
	{
		$collection = new static();

		if ($parent !== null)
		{
			$collection->setParent($parent);
		}

		foreach ($dataList as $data)
		{
			$collection->createItem($data);
		}

		return $collection;
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function setParent(Model $model)
	{
		$this->parent = $model;
	}

	public function addItem(Model $model)
	{
		$this->collection[] = $model;
	}

	public function removeItem(Model $model)
	{
		$index = array_search($model, $this->collection,  true);

		if ($index === false) { return; }

		array_splice($this->collection, $index, 1);
	}

	public function createItem($data)
	{
		$modelClassName = static::getItemReference();

		Market\Reference\Assert::notNull($modelClassName, 'itemReference');
		Market\Reference\Assert::isSubclassOf($modelClassName, Model::class);

		$model = $modelClassName::initialize($data);
		$model->setParentCollection($this);

		$this->addItem($model);

		return $model;
	}

	public function getItemById($id)
	{
		$result = null;

		foreach ($this->collection as $item)
		{
			if ((string)$item->getId() === (string)$id)
			{
				$result = $item;
				break;
			}
		}

		return $result;
	}

	public function exceptItemId($id)
	{
		return $this->filter(function(Model $item) use ($id) {
			return (string)$item->getId() !== (string)$id;
		});
	}

	public function filter($filter)
	{
		$filtered = new static();

		if ($this->parent !== null) { $filtered->setParent($this->parent); }

		foreach ($this->collection as $item)
		{
			if (!$this->applyFilter($item, $filter)) { continue; }

			$filtered->addItem($item);
		}

		return $filtered;
	}

	protected function applyFilter(Model $setup, $filter)
	{
		if ($filter === null)
		{
			$result = true;
		}
		else if (is_array($filter))
		{
			$result = true;

			foreach ($filter as $key => $value)
			{
				if ((string)$setup->getField($key) !== (string)$value)
				{
					$result = false;
					break;
				}
			}
		}
		else if (is_string($filter))
		{
			$fieldValue = (string)$setup->getField($filter);

			$result = (
				$fieldValue === Market\Reference\Storage\Table::BOOLEAN_Y
				|| $fieldValue === 'Y'
			);
		}
		else if (is_callable($filter))
		{
			/** @noinspection VariableFunctionsUsageInspection */
			$result = call_user_func($filter, $setup);
		}
		else
		{
			throw new Main\NotImplementedException('unknown filter type');
		}

		return $result;
	}

	public function getItemIndex(Model $item)
	{
		$key = array_search($item, $this->collection, true);

		return $key !== false ? $key : null;
	}

	public function toArray()
	{
		$result = [];

		foreach ($this->collection as $item)
		{
			$result[] = $item->getFields();
		}

		return $result;
	}
}