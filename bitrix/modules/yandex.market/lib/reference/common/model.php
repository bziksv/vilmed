<?php
namespace Yandex\Market\Reference\Common;

use Bitrix\Main;

abstract class Model
{
	use ModelCompatible;

	protected static $internalIndex = 0;

	/** @var string */
	protected $internalId;
	/** @var array */
	protected $fields;
	/** @var Model */
	protected $parent;
	/** @var Collection */
	protected $parentCollection;
	/** @var Collection[] */
	protected $childCollection = [];
	/** @var Model[] */
	protected $childModel = [];

	public static function initialize($fields)
	{
		return new static($fields);
	}

	public function __construct(array $fields = [])
	{
		$this->fields = $fields;
	}

	/** @return array */
	public function getFields()
	{
		return $this->fields;
	}

	/** @return bool */
	public function hasField($name)
	{
		return array_key_exists($name, $this->fields);
	}

	public function getField($name)
	{
		return isset($this->fields[$name]) ? $this->fields[$name] : null;
	}

	public function setFields(array $values)
	{
		foreach ($values as $name => $value)
		{
			$this->setField($name, $value);
		}
	}

	public function setField($name, $value)
	{
		$this->fields[$name] = $value;
	}

	public function getId()
	{
		return $this->getField('ID');
	}

	public function getInternalId()
	{
		$id = $this->getId();

		if ($id !== null && $id !== '')
		{
			// nothing
		}
		else if ($this->internalId !== null)
		{
			$id = $this->internalId;
		}
		else
		{
			$id = 'n' . static::$internalIndex;
			$this->internalId = $id;

			++static::$internalIndex;
		}

		return $id;
	}

	public function setParent(Model $parent)
	{
		$this->parent = $parent;
	}

	/** @return Model|null */
	public function getParent()
	{
		if ($this->parent !== null)
		{
			return $this->parent;
		}

		if ($this->parentCollection !== null)
		{
			return $this->parentCollection->getParent();
		}

		return null;
	}

	public function setParentCollection(Collection $collection)
	{
		$this->parentCollection = $collection;
	}

	/** @return Collection */
	public function getParentCollection()
	{
		return $this->parentCollection;
	}

    /**
     * @template T
     * @param string $fieldKey
     * @param class-string<T> $className
     * @return T
     */
    protected function getCollection($fieldKey = null, $className = null)
	{
		if ($fieldKey === null)
		{
			trigger_error('use getParentCollection for parent reference', E_USER_WARNING);

			return $this->getParentCollection();
		}

		if (!isset($this->childCollection[$fieldKey]))
		{
			$this->childCollection[$fieldKey] = $this->buildCollection($fieldKey, $className);
		}

		return $this->childCollection[$fieldKey];
	}

	protected function buildCollection($fieldKey, $className)
	{
		throw new Main\NotImplementedException('buildCollection is missing for ' . static::class);
	}

    /**
     * @template T
     * @param string $fieldKey
     * @param class-string<T> $className
     * @return T|null
     */
	protected function getModel($fieldKey, $className)
	{
		if (!isset($this->childModel[$fieldKey]))
		{
			$this->childModel[$fieldKey] = $this->buildModel($fieldKey, $className);
		}

		return $this->childModel[$fieldKey];
	}

	protected function buildModel($fieldKey, $className)
	{
		throw new Main\NotImplementedException('buildModel is missing for ' . static::class);
	}
}