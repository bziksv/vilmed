<?php
namespace Yandex\Market\Trading\Service\Reference\Options;

use Yandex\Market\Trading\Settings\Options;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading\Service as TradingService;

abstract class Skeleton extends Options
{
	protected $provider;
	protected $fieldset = [];
	protected $fieldsetCollection = [];

	public function __construct(TradingService\Reference\Provider $provider)
	{
		$this->provider = $provider;
	}

	public function __clone()
	{
		foreach ($this->fieldset as $key => $fieldset)
		{
			$this->fieldset[$key] = clone $fieldset;
		}

		foreach ($this->fieldsetCollection as $key => $fieldsetCollection)
		{
			$this->fieldsetCollection[$key] = clone $fieldsetCollection;
		}
	}

	public function extendValues(array $values)
	{
		$values = array_merge((array)$this->values, $values);

		$this->setValues($values);
	}

	public function setValues(array $values)
	{
		$leftValues = $this->setFieldsetValues($values);
		$leftValues = $this->setFieldsetCollectionValues($leftValues);

		$this->values = $leftValues;
		$this->applyValues();
	}

	protected function setFieldsetValues(array $values)
	{
		$found = [];

		foreach ($this->knownFieldsets() as $fieldset)
		{
			$key = array_search($fieldset, $this->fieldset, true);
			$found[$key] = true;
			$fieldsetValues = isset($values[$key]) && is_array($values[$key]) ? $values[$key] : [];

			$fieldset->setValues($fieldsetValues);
		}

		return array_diff_key($values, $found);
	}

	protected function setFieldsetCollectionValues(array $values)
	{
		$found = [];

		foreach ($this->knownFieldsetCollections() as $collection)
		{
			$key = array_search($collection, $this->fieldsetCollection, true);
			$found[$key] = true;
			$fieldsetValues = isset($values[$key]) && is_array($values[$key]) ? $values[$key] : [];

			$collection->setValues($fieldsetValues);
		}

		return array_diff_key($values, $found);
	}

	public function getRequiredValue($key, $default = null)
	{
		return $this->requireValue($key, $default);
	}

	public function getValues()
	{
		$result = $this->values;
		$result += $this->getFieldsetValues();
		$result += $this->getFieldsetCollectionValues();

		return $result;
	}

	protected function getFieldsetValues()
	{
		$result = [];

		foreach ($this->knownFieldsets() as $fieldset)
		{
			$key = array_search($fieldset, $this->fieldset, true);

			$result[$key] = $fieldset->getValues();
		}

		return $result;
	}

	protected function getFieldsetCollectionValues()
	{
		$result = [];

		foreach ($this->knownFieldsetCollections() as $collection)
		{
			$key = array_search($collection, $this->fieldsetCollection, true);
			$result[$key] = $collection->getValues();
		}

		return $result;
	}

	public function getPlacementValues($placement)
	{
		$result = $this->values;
		$result += $this->getFieldsetPlacementValues($placement);
		$result += $this->getFieldsetCollectionPlacementValues($placement);

		return $this->modifyPlacementValues($placement, $result);
	}

	protected function modifyPlacementValues($placement, array $values)
	{
		return $values;
	}

	protected function getFieldsetPlacementValues($placement)
	{
		$result = [];

		foreach ($this->knownFieldsets() as $fieldset)
		{
			$key = array_search($fieldset, $this->fieldset, true);

			$result[$key] = $fieldset->getPlacementValues($placement);
		}

		return $result;
	}

	protected function getFieldsetCollectionPlacementValues($placement)
	{
		$result = [];

		foreach ($this->knownFieldsetCollections() as $collection)
		{
			$key = array_search($collection, $this->fieldsetCollection, true);
			$result[$key] = $collection->getPlacementValues($placement);
		}

		return $result;
	}

	/** @return Fieldset[] */
	protected function knownFieldsets()
	{
		return [];
	}

	/**
	 * @template T of Fieldset
	 *
	 * @param string $key
	 * @param class-string<T> $className
	 *
	 * @return T
	 */
	protected function getFieldset($key, $className)
	{
		if (!isset($this->fieldset[$key]))
		{
			Assert::isSubclassOf($className, Fieldset::class);

			$this->fieldset[$key] = new $className($this->provider);
		}

		return $this->fieldset[$key];
	}

	/** @return FieldsetCollection[] */
	protected function knownFieldsetCollections()
	{
		return [];
	}

	/**
	 * @template T of FieldsetCollection
	 *
	 * @param string $key
	 * @param class-string<T> $className
	 *
	 * @return T
	 */
	protected function getFieldsetCollection($key, $className)
	{
		if (!isset($this->fieldsetCollection[$key]))
		{
			Assert::isSubclassOf($className, FieldsetCollection::class);

			$this->fieldsetCollection[$key] = new $className($this->provider);
		}

		return $this->fieldsetCollection[$key];
	}
}