<?php
namespace Yandex\Market\Api\Reference;

use Yandex\Market;

abstract class Model extends Market\Reference\Common\Model
{
	use ModelCompatible;

	protected $relativePath;

	public static function initialize($fields, $relativePath = '')
	{
		$result = parent::initialize($fields);
		$result->setRelativePath($relativePath);

		return $result;
	}

	public function setRelativePath($path)
	{
		$this->relativePath = $path;
	}

	public function getId()
	{
		return $this->getField('id');
	}

	public function hasField($name)
	{
		return Market\Utils\Field::hasChainValue($this->fields, $name);
	}

	public function getField($name)
	{
		return Market\Utils\Field::getChainValue($this->fields, $name);
	}

	protected function requireField($name)
	{
		$value = $this->getField($name);

		if ($value === null || $value === '')
		{
			throw new Market\Exceptions\Api\ObjectPropertyException($this->relativePath . $name);
		}

		return $value;
	}

	/**
	 * @template T
	 * @param string $fieldKey
	 * @param class-string<T> $className
	 *
	 * @return T
	 */
	protected function requireCollection($fieldKey, $className)
	{
        if (!$this->hasField($fieldKey))
        {
            throw new Market\Exceptions\Api\ObjectPropertyException($this->relativePath . $fieldKey);
        }

		return $this->getCollection($fieldKey, $className);
	}

	protected function buildCollection($fieldKey, $className)
	{
		$childPath = $this->relativePath . $fieldKey;
        $dataList = $this->hasField($fieldKey) ? (array)$this->getField($fieldKey) : [];

		return $className::initialize($dataList, $this, $childPath);
	}

	/**
	 * @template T
	 * @param string $fieldKey
	 * @param class-string<T> $className
	 *
	 * @return T
	 */
	protected function requireModel($fieldKey, $className)
	{
		$result = $this->getModel($fieldKey, $className);

		if ($result === null)
		{
			throw new Market\Exceptions\Api\ObjectPropertyException($this->relativePath . $fieldKey);
		}

		return $result;
	}

	/**
	 * @template T
	 * @param string $fieldKey
	 * @param class-string<T> $className
	 *
	 * @return T
	 */
	protected function anyModel($fieldKey, $className)
	{
		if (isset($this->childModel[$fieldKey])) { return $this->childModel[$fieldKey]; }

		$model = $this->getModel($fieldKey, $className);

		if ($model !== null) { return $model; }

		$result = $this->compileModel([], $fieldKey, $className);

		$this->childModel[$fieldKey] = $result;

		return $result;
	}

	protected function buildModel($fieldKey, $className)
	{
		if (!$this->hasField($fieldKey)) { return null; }

        $data = (array)$this->getField($fieldKey);

		return $this->compileModel($data, $fieldKey, $className);
	}

	protected function compileModel($data, $fieldKey, $className)
	{
		Market\Reference\Assert::isSubclassOf($className, self::class);

		$path = $this->relativePath . $fieldKey . '.';

		/** @var Model $result */
		$result = $className::initialize($data, $path);
		$result->setParent($this);

		return $result;
	}
}