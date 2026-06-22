<?php
namespace Yandex\Market\Api\Reference;

use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Reference\Assert;

abstract class RequestTokenized extends Request
{
	/** @var Auth */
	protected $auth;

	public function __construct($auth = null, LoggerInterface $logger = null)
	{
		parent::__construct($logger);
		$this->auth = $this->authFactory($auth);
	}

	private function authFactory($auth)
	{
		if ($auth === null) // wait delayed setOauthToken
		{
			return new ApiKey('');
		}

		if ($auth instanceof HasAuth)
		{
			return $auth->getApiAuth();
		}

		if ($auth instanceof HasOauthConfiguration)
		{
			return new OAuth($auth->getOauthToken()->getAccessToken());
		}

		Assert::isInstanceOf($auth, Auth::class);

		return $auth;
	}

	/**
     * @deprecated
     * @noinspection PhpUnused
     */
	public function setOauthToken($oauthToken)
	{
		$this->auth = new OAuth($oauthToken);
	}

	/**
     * @deprecated
     * @noinspection PhpUnused
     */
	public function getOauthToken() {}

	protected function buildClient()
	{
		$result = parent::buildClient();
		$result->setHeader(...$this->auth->getHeader());

		return $result;
	}
}