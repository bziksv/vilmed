<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model;

use Yandex\Market\Api;
use Yandex\Market\Trading\Service as TradingService;

class OrderFacade extends Api\Model\OrderFacade
{
	protected static function createLoadListRequest(TradingService\Common\Options $options)
	{
		return new TradingService\Marketplace\Api\Orders\Request($options->getCampaignId(), $options);
	}

	protected static function createLoadRequest(TradingService\Common\Options $options)
	{
		return new TradingService\Marketplace\Api\Order\Request($options->getCampaignId(), $options);
	}
}