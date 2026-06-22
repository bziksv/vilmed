<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness;

use Yandex\Market\Trading;
use Yandex\Market\Trading\Service;
use Yandex\Market\Reference\Concerns;

/**
 * @property Provider $provider
*/
class Installer extends Service\Reference\Installer
{
	use Concerns\HasMessage;

	public function install()
	{
		$tradingContext = $this->provider->getContext();

		/** @var Trading\Campaign\Model $campaign */
		foreach ($tradingContext->getCampaignCollection() as $campaign)
		{
			$campaignProvider = $this->provider->getCampaignFactory()->getProvider($campaign);
			$campaignProvider->getInstaller()->install();
		}
	}

	public function uninstall(array $context = [])
	{
		$tradingContext = $this->provider->getContext();

		/** @var Trading\Campaign\Model $campaign */
		foreach ($tradingContext->getCampaignCollection() as $campaign)
		{
			$campaignProvider = $this->provider->getCampaignFactory()->getProvider($campaign);
			$campaignProvider->getInstaller()->uninstall($context);
		}
	}

	public function onCatalogSubmitted()
	{
		$tradingContext = $this->provider->getContext();

		/** @var Trading\Campaign\Model $campaign */
		foreach ($tradingContext->getCampaignCollection() as $campaign)
		{
			$campaignProvider = $this->provider->getCampaignFactory()->getProvider($campaign);
			$campaignProvider->getInstaller()->onCatalogSubmitted();
		}
	}
}