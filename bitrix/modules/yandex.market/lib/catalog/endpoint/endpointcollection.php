<?php
namespace Yandex\Market\Catalog\Endpoint;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;

/** @property Endpoint[] $collection */
class EndpointCollection implements \ArrayAccess, \Countable, \IteratorAggregate
{
	use Concerns\HasOnce;
	use Concerns\HasCollection;

	/** @param Endpoint[] $endpoints */
	public function __construct(array $endpoints)
	{
		$this->collection = $this->mapCollection($endpoints);
	}

	/**
	 * @param string $key
	 * @return Endpoint|null
	 */
	public function getItem($key)
	{
		if (!isset($this->collection[$key])) { return null; }

		return $this->collection[$key];
	}

	/**
	 * @param string $key
	 * @return Endpoint
	 */
	public function requireItem($key)
	{
		if (!isset($this->collection[$key]))
		{
			throw new Main\ArgumentException(sprintf('endpoint %s not found in collection', $key));
		}

		return $this->collection[$key];
	}

	/** @param Endpoint[] $endpoints */
	private function mapCollection(array $endpoints)
	{
		$result = [];

		foreach ($endpoints as $endpoint)
		{
			$primary = (string)$endpoint->getPrimary();

			if (isset($result[$primary]))
			{
				throw new Main\ArgumentException(sprintf('endpoint %s already registered', $primary));
			}

			$result[$primary] = $endpoint;
		}

		return $result;
	}
}