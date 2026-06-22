<?php
namespace Yandex\Market\Api\Reference;

use Yandex\Market\Reference\Assert;

class OAuth implements Auth
{
    const HEADER_NAME = 'Authorization';

	private $accessToken;

	/** @param string $accessToken */
	public function __construct($accessToken)
	{
		$this->accessToken = $accessToken;
	}

	public function setAccessToken($accessToken)
	{
		$this->accessToken = $accessToken;
	}

	public function getHeader()
	{
		Assert::nonEmptyString($this->accessToken, 'accessToken');

		return [ self::HEADER_NAME, "Bearer {$this->accessToken}" ];
	}
}