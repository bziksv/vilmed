<?php
/** @noinspection PhpDeprecationInspection */
namespace Yandex\Market\Trading\Service\Turbo;

use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;

/** @deprecated  */
class Provider extends TradingService\Reference\Provider
{
	protected $status;
	protected $paySystem;

	public function getServiceCode()
	{
		return TradingService\Manager::SERVICE_TURBO;
	}

	protected function createInstaller()
	{
		return new Installer($this);
	}

	protected function createInfo()
	{
		return new Info($this);
	}

	protected function createOptions()
	{
		return new Options($this);
	}

	protected function createStatus()
	{
		throw new Main\NotImplementedException('status for turbo removed');
	}

	protected function createRouter()
	{
		throw new Main\NotImplementedException('router for turbo removed');
	}
}