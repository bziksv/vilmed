<?php
namespace Yandex\Market\Trading\Service\Common;

use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading\Setup\CampaignContext;
use Yandex\Market\Trading\Setup\TradingContext;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @method CampaignContext getContext()
 * @method Options getOptions()
 */
abstract class Provider extends TradingService\Reference\Provider
{
	public function wakeup(TradingContext $context, array $optionValues)
	{
		Assert::isInstanceOf($context, CampaignContext::class);

		parent::wakeup($context, $optionValues);
	}
}