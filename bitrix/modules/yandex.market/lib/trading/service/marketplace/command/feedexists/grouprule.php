<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command\FeedExists;

use Yandex\Market\Trading\Service as TradingService;

class GroupRule implements Rule
{
	protected $provider;

	public function __construct(TradingService\Marketplace\Provider $provider)
	{
		$this->provider = $provider;
	}

	public function getFeeds()
	{
		return $this->provider->getOptions()->getProductFeeds();
	}
}