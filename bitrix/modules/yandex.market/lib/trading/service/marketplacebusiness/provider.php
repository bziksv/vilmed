<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading\Service;
use Yandex\Market\Trading\Setup\TradingContext;
use Yandex\Market\Trading\Setup\BusinessContext;

/**
 * @method Options getOptions()
 * @method BusinessContext getContext()
 */
class Provider extends Service\Reference\Provider
	implements Service\Reference\HasCampaignFactory
{
	protected $campaignFactory;

	public function getServiceCode()
	{
		return Service\Manager::SERVICE_MARKETPLACE;
	}

	public function getBehaviorCode()
	{
		return Service\Manager::BEHAVIOR_BUSINESS;
	}

	public function wakeup(TradingContext $context, array $optionValues)
	{
		Assert::isInstanceOf($context, BusinessContext::class);

		parent::wakeup($context, $optionValues);

		$this->getCampaignFactory()->resetContext();
	}

	protected function createInfo()
	{
		return new Info($this);
	}

	protected function createOptions()
	{
		return new Options($this);
	}

	protected function createInstaller()
	{
		return new Installer($this);
	}

	protected function createRouter()
	{
		throw new Main\NotImplementedException(static::class . '::createRouter not implemented');
	}

	protected function createStatus()
	{
		throw new Main\NotImplementedException(static::class . '::createStatus not implemented');
	}

	public function getCampaignFactory()
	{
		if ($this->campaignFactory === null)
		{
			$this->campaignFactory = $this->createCampaignFactory();
		}

		return $this->campaignFactory;
	}

	protected function createCampaignFactory()
	{
		return new CampaignFactory($this);
	}
}