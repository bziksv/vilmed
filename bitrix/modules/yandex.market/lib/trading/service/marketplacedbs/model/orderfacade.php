<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class OrderFacade extends Market\Api\Model\OrderFacade
{
	protected static function createLoadListRequest(TradingService\Common\Options $options)
	{
		return new TradingService\MarketplaceDbs\Api\Orders\Request($options->getCampaignId(), $options);
	}

	protected static function createLoadRequest(TradingService\Common\Options $options)
	{
		return new TradingService\MarketplaceDbs\Api\Order\Request($options->getCampaignId(), $options);
	}

	protected static function createSubmitStatusRequest(TradingService\Common\Options $options)
	{
		return  new TradingService\MarketplaceDbs\Api\SendStatus\Request($options->getCampaignId(), $options);
	}
}