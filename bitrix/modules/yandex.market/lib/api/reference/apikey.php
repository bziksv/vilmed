<?php
namespace Yandex\Market\Api\Reference;

use Yandex\Market\Reference\Assert;

class ApiKey implements Auth
{
    const HEADER_NAME = 'Api-Key';

	private $key;

	/** @param string $key */
	public function __construct($key)
	{
		$this->key = $key;
	}

	public function getHeader()
	{
		Assert::nonEmptyString($this->key, 'apiKey');

		return [ self::HEADER_NAME, $this->key ];
	}
}