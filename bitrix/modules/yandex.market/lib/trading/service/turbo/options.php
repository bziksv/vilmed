<?php
namespace Yandex\Market\Trading\Service\Turbo;

use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;

/** @deprecated  */
class Options extends TradingService\Reference\Options
{
	public function getTabs()
	{
		throw new Main\NotImplementedException('options for turbo removed');
	}

	public function getFields()
	{
		throw new Main\NotImplementedException('options for turbo removed');
	}
}