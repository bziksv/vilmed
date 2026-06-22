<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Campaign\Placement;
use Yandex\Market\Trading\Service as TradingService;

/** @property Provider $provider */
class CampaignFactory extends TradingService\Reference\CampaignFactory
{
	use Concerns\HasOnce;

	protected $campaignCache = [];
	protected $placementCache = [];

	public function getProvider(Campaign\Model $campaign)
	{
		$id = $campaign->getId();

		if (isset($this->campaignCache[$id])) { return $this->campaignCache[$id]; }

		$placement = $campaign->getPlacement();
		$behavior = Placement::toBehavior($placement) ?: TradingService\Manager::BEHAVIOR_DEFAULT;

		/** @var TradingService\Marketplace\Provider  $campaignProvider */
		$campaignProvider = TradingService\Manager::createProvider($this->provider->getServiceCode(), $behavior);
		$campaignProvider->wakeup(
			$this->provider->getContext()->makeCampaignContext($campaign),
			$this->provider->getOptions()->getPlacementValues($placement)
		);

		$this->campaignCache[$id] = $campaignProvider;
		$this->placementCache[$id] = $placement;

		return $campaignProvider;
	}

	public function resetContext()
	{
		/** @var TradingService\Marketplace\Provider $campaignProvider */
		foreach ($this->campaignCache as $id => $campaignProvider)
		{
			$campaignProvider->wakeup(
				$this->provider->getContext()->makeCampaignContext($campaignProvider->getContext()->getCampaign()),
				$this->provider->getOptions()->getPlacementValues($this->placementCache[$id])
			);
		}
	}
}