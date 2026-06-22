<?php
namespace Yandex\Market\Api\Partner\Reference;

use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Api\Reference\Transport;
use Yandex\Market\Psr\Log\LoggerInterface;

abstract class BusinessRequest extends Api\Reference\RequestTokenized
{
	protected $businessId;

	public function __construct($businessId = null, $auth = null, LoggerInterface $logger = null)
	{
		parent::__construct($auth, $logger);
		$this->businessId = $businessId;
	}

	public function getHost()
	{
		return Api\Glossary::MARKET_API_HOST;
	}

	public function setBusinessId($businessId)
	{
		$this->businessId = $businessId;
	}

	public function getBusinessId()
	{
		Assert::notNull($this->businessId, 'businessId');

		return (string)$this->businessId;
	}

	protected function createLocker()
	{
        $key = $this->getHost() . '_' . md5(implode('_', $this->auth->getHeader()));
        $limit = 2;

		return new Transport\Locker(new Api\Locker($key, $limit));
	}
}