<?php
/** @noinspection PhpDeprecationInspection */
namespace Yandex\Market\Trading\Service\Beru;

use Yandex\Market\Trading\Service;

/** @deprecated */
class Provider extends Service\Marketplace\Provider
{
	public function getServiceCode()
	{
		return Service\Manager::SERVICE_BERU;
	}

	protected function createInfo()
	{
		return new Info($this);
	}

	protected function createOptions()
	{
		return new Options($this);
	}
}
