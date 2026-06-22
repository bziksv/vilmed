<?php

namespace Yandex\Market\Reference\Concerns;

trait HasCollection
{
	protected $collection = [];

	#[\ReturnTypeWillChange]
	public function getIterator()
	{
		return new \ArrayIterator($this->collection);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return isset($this->collection[$offset]) || array_key_exists($offset, $this->collection);
	}


	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		if (isset($this->collection[$offset]) || array_key_exists($offset, $this->collection))
		{
			return $this->collection[$offset];
		}

		return null;
	}


	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value)
	{
		if($offset === null)
		{
			$this->collection[] = $value;
		}
		else
		{
			$this->collection[$offset] = $value;
		}
	}


	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
	{
		unset($this->collection[$offset]);
	}

	#[\ReturnTypeWillChange]
	public function count()
	{
		return count($this->collection);
	}

	/**
	 * Return the current element
	 */
	public function current()
	{
		return current($this->collection);
	}

	/**
	 * Move forward to next element
	 */
	public function next()
	{
		return next($this->collection);
	}

	/**
	 * Return the key of the current element
	 */
	public function key()
	{
		return key($this->collection);
	}

	/**
	 * Checks if current position is valid
	 */
	public function valid()
	{
		$key = $this->key();
		return $key !== null;
	}

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind()
	{
		return reset($this->collection);
	}

	public function asArray()
	{
		return $this->collection;
	}
}