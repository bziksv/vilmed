<?php

namespace Yandex\Market\Reference;

use Bitrix\Main;

class Assert
{
	public static function notNull($value, $argument, $message = null)
	{
		if ($value === null)
		{
			$message = $message !== null ? $message : sprintf('Argument "%s" is null', $argument);

			throw new Main\ArgumentException($message, $argument);
		}
	}

	public static function notEmpty($value, $argument, $message = null)
	{
		if (empty($value))
		{
			$message = $message !== null ? $message : sprintf('Argument "%s" is empty', $argument);

			throw new Main\ArgumentException($message, $argument);
		}
	}

	public static function typeOf($value, $className, $argument)
	{
		if (!($value instanceof $className))
		{
			throw new Main\ArgumentTypeException($argument, $className);
		}
	}

	public static function isArray($value, $argument)
	{
		if (!is_array($value))
		{
			throw new Main\ArgumentTypeException($argument, 'Array');
		}
	}

	public static function classExists($className)
	{
		if (!class_exists($className))
		{
			throw new Main\NotImplementedException(sprintf('class %s not exists', $className));
		}
	}

	public static function isInstanceOf($object, $parentClass)
	{
		if (!($object instanceof $parentClass))
		{
			$type = gettype($object);
			if ($type === 'object') { $type = get_class($object); }

			throw new Main\InvalidOperationException(sprintf('%s must extends %s', $type, $parentClass));
		}
	}

	public static function isSubclassOf($className, $parentClass)
	{
		if (!is_subclass_of($className, $parentClass))
		{
			throw new Main\InvalidOperationException(sprintf(
				'%s must extends %s',
				$className,
				$parentClass
			));
		}
	}

	public static function methodExists($classOrObject, $method)
	{
		if (!method_exists($classOrObject, $method))
		{
			throw new Main\InvalidOperationException(sprintf(
				'Class %s method %s is missing',
				is_object($classOrObject) ? get_class($classOrObject) : $classOrObject,
				$method
			));
		}
	}

	public static function positiveInteger($value, $argument)
	{
		if (!is_numeric($value))
		{
			throw new Main\ArgumentTypeException($argument, 'integer');
		}

		if ((int)$value <= 0)
		{
			throw new Main\ArgumentException(sprintf('%s must be positive', $argument));
		}
	}

	public static function nonEmptyString($value, $argument)
	{
		if (!is_scalar($value))
		{
			throw new Main\ArgumentTypeException($argument, 'string');
		}

		$value = (string)$value;

		if ($value === '')
		{
			throw new Main\ArgumentException(sprintf('%s must be non empty string', $argument));
		}
	}
}