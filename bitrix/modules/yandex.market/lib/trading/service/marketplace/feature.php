<?php
namespace Yandex\Market\Trading\Service\Marketplace;

use Yandex\Market\Trading\Service as TradingService;

class Feature extends TradingService\Reference\Feature
{
	public function supportsCis()
	{
		return true;
	}

	public function supportBoxesWithoutItems()
	{
		return false;
	}
}