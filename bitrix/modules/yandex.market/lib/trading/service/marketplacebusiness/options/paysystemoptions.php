<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness\Options;

use Yandex\Market\Trading\Service as TradingService;

/** @method PaySystemOption current() */
class PaySystemOptions extends TradingService\Reference\Options\FieldsetCollection
{
	public function getItemReference()
	{
		return PaySystemOption::class;
	}
}