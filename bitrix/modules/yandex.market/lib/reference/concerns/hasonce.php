<?php

namespace Yandex\Market\Reference\Concerns;

use Yandex\Market;

trait HasOnce
{
	private $onceMemoized = [];

	/**
	 * @template T
	 *
	 * @param string $name
	 * @param array|null $arguments
	 * @param (callable(): T)|null $callable
	 *
	 * @return T
	 */
	protected function once($name, $arguments = null, $callable = null)
	{
		if ($callable === null && is_callable($arguments))
		{
			$callable = $arguments;
			$arguments = null;
		}

		$hash = Market\Utils\Caller::getArgumentsHash($arguments);

		if (!isset($this->onceMemoized[$name])) { $this->onceMemoized[$name] = []; }

		if (!isset($this->onceMemoized[$name][$hash]) && !array_key_exists($hash, $this->onceMemoized[$name]))
		{
			$this->onceMemoized[$name][$hash] = $this->callOnce($name, $arguments, $callable);
		}

		return $this->onceMemoized[$name][$hash];
	}

    /**
     * @param string $name
     */
    protected function clearOnce($name)
    {
        if (isset($this->onceMemoized[$name]))
        {
            $this->onceMemoized[$name] = null;
        }
    }

	/**
	 * @template T
	 *
	 * @param string $name
	 * @param array|null $arguments
	 * @param (callable(): T)|null $callable
	 *
	 * @return T
	 * @noinspection VariableFunctionsUsageInspection
	 */
	private function callOnce($name, $arguments = null, $callable = null)
	{
		if ($arguments === null)
		{
			$result = $callable !== null
				? call_user_func($callable)
				: $this->{$name}();
		}
		else if (is_array($arguments))
		{
			$result = $callable !== null
				? call_user_func_array($callable, $arguments)
				: $this->{$name}(...$arguments);
		}
		else
		{
			$result = $callable !== null
				? call_user_func($callable, $arguments)
				: $this->{$name}($arguments);
		}

		return $result;
	}
}