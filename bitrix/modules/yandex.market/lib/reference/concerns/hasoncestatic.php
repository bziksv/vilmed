<?php
namespace Yandex\Market\Reference\Concerns;

use Yandex\Market\Utils\Caller;

trait HasOnceStatic
{
	private static $onceMemoizedStatic = [];

	protected static function onceStatic($name, $arguments = null, $callable = null)
	{
		if ($callable === null && is_callable($arguments))
		{
			$callable = $arguments;
			$arguments = null;
		}

		$hash = Caller::getArgumentsHash($arguments);

		if (!isset(self::$onceMemoizedStatic[$name])) { self::$onceMemoizedStatic[$name] = []; }

		if (!isset(self::$onceMemoizedStatic[$name][$hash]) && !array_key_exists($hash, self::$onceMemoizedStatic))
		{
			self::$onceMemoizedStatic[$name][$hash] = static::callOnceStatic($name, $arguments, $callable);
		}

		return self::$onceMemoizedStatic[$name][$hash];
	}

	protected static function clearOnceStatic($name)
	{
		if (isset(self::$onceMemoizedStatic[$name]))
		{
			self::$onceMemoizedStatic[$name] = null;
		}
	}

	/** @noinspection VariableFunctionsUsageInspection */
	private static function callOnceStatic($name, $arguments = null, $callable = null)
	{
		if ($arguments === null)
		{
			$result = $callable !== null
				? call_user_func($callable)
				: static::{$name}();
		}
		else if (is_array($arguments))
		{
			$result = $callable !== null
				? call_user_func_array($callable, $arguments)
				: static::{$name}(...$arguments);
		}
		else
		{
			$result = $callable !== null
				? call_user_func($callable, $arguments)
				: static::{$name}($arguments);
		}

		return $result;
	}
}