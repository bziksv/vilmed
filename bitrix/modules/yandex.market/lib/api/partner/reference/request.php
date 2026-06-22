<?php
namespace Yandex\Market\Api\Partner\Reference;

use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Api\Reference\Transport;
use Yandex\Market\Psr\Log\LoggerInterface;

abstract class Request extends Api\Reference\RequestTokenized
{
	protected $campaignId;

	public function __construct($campaignId = null, $auth = null, LoggerInterface $logger = null)
	{
		parent::__construct($auth, $logger);
		$this->campaignId = $campaignId;
	}

	public function getHost()
	{
		return Api\Glossary::MARKET_API_HOST;
	}

	public function setCampaignId($campaignId)
	{
		$this->campaignId = $campaignId;
	}

	public function getCampaignId()
	{
		Assert::notNull($this->campaignId, 'campaignId');

		return (string)$this->campaignId;
	}

	protected function createLocker()
	{
		$key = $this->getHost() . '_' . md5(implode('_', $this->auth->getHeader()));
		$limit = 2;

		return new Transport\Locker(new Api\Locker($key, $limit));
	}
}