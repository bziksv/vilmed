<?php
namespace Yandex\Market\Trading\Business;

use Yandex\Market\Trading\Entity;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Service;
use Yandex\Market\Trading\Setup;
use Yandex\Market\Ui\Trading\MenuCompiler;

class TradingRepository
{
	private $business;

	public function __construct(Model $business)
	{
		$this->business = $business;
	}

	/* sync with \Yandex\Market\Trading\Service\MarketplaceBusiness\Options::getKnownPlacements() */
	public function getBusinessPlacements()
	{
		return [
			Campaign\Placement::FBS,
			Campaign\Placement::DBS,
		];
	}

	public function getBusinessCampaignCollection()
	{
		$tradingCollection = $this->business->getTradingCollection();
		$placements = $this->getBusinessPlacements();

		return $this->business->getCampaignCollection()->filter(function(Campaign\Model $campaign) use ($tradingCollection, $placements) {
			if (!in_array($campaign->getPlacement(), $placements, true))
			{
				return false;
			}

			$trading = $tradingCollection->getItemByCampaignId($campaign->getId());

			return ($trading === null || !$trading->isActive());
		});
	}

	public function linkMenuBusinessBehavior(Setup\Model $trading)
	{
		$menuCompiler = new MenuCompiler();

		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			$menuCompiler->injectBusinessBehavior($this->business->getId());
		}
		else
		{
			$menuCompiler->injectCampaignBehavior($this->business->getId());
		}

		$menuCompiler->save();
	}

	public function unlinkMenuBusinessBehavior(Setup\Model $trading)
	{
		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS) { return; }

		$siblings = $this->business->getTradingCollection()->exceptItemId($trading->getId());

		if ($siblings->getCampaignItems()->filterActive()->count() === 0)
		{
			$menuCompiler = new MenuCompiler();
			$menuCompiler->ejectCampaignBehavior($this->business->getId());
			$menuCompiler->save();
		}
	}

	public function installPlatform(Entity\Reference\Environment $environment)
	{
		$platform = $this->environmentPlatform($environment);
		$platform->install($this->business);
		$platform->activate();

		$platformId = $platform->getId();

		$this->business->setField('PLATFORM_ID', $platformId);
		$this->business->save();

		return $platformId;
	}

	public function migratePlatform(Entity\Reference\Environment $environment, $platformId)
	{
		$this->environmentPlatform($environment)->migrate($platformId, $this->business);
		$this->business->setField('PLATFORM_ID', $platformId);
		$this->business->save();

		return $platformId;
	}

	public function unlinkPlatform(Entity\Reference\Environment $environment, $tradingId)
	{
		if ($this->hasActiveCampaign($tradingId)) { return; }

		$this->deactivatePlatform($environment);
	}

	private function hasActiveCampaign($excludeTradingId)
	{
		$excludeTradingId = (int)$excludeTradingId;

		/** @var Campaign\Model $campaign */
		foreach ($this->business->getCampaignCollection() as $campaign)
		{
			$tradingId = $campaign->getTradingId();

			if ($tradingId > 0 && $tradingId !== $excludeTradingId)
			{
				return true;
			}
		}

		return false;
	}

	public function deactivatePlatform(Entity\Reference\Environment $environment)
	{
		$platform = $this->environmentPlatform($environment);

		if (!$platform->isInstalled()) { return; }

		$platform->deactivate();
	}

	private function environmentPlatform(Entity\Reference\Environment $environment)
	{
		return $environment->getPlatformRegistry()->getPlatform($this->business->getId());
	}

	public function someoneUsingBehavior($behavior, $excludeSetupId = null)
	{
		/** @var Setup\Model $trading */
		foreach ($this->business->getTradingCollection() as $trading)
		{
			if ($excludeSetupId !== null && (int)$excludeSetupId === $trading->getId()) { continue; }

			if (
				$trading->isActive()
				&& (
					$trading->getBehaviorCode() === $behavior
					|| $trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS
				)
			)
			{
				return true;
			}
		}

		return false;
	}

	public function uninstallBusinessCampaign($campaignId)
	{
		$businessTrading = $this->business->getTradingCollection()->getByBehavior(Service\Manager::BEHAVIOR_BUSINESS);
		$campaign = $this->business->getCampaignCollection()->getItemById($campaignId);

		if ($campaign === null || $businessTrading === null || !$businessTrading->isActive()) { return; }

		$businessTrading->bootCampaign($campaign)->uninstall();
	}

	public function installBusinessCampaign($campaignId)
	{
		$businessTrading = $this->business->getTradingCollection()->getByBehavior(Service\Manager::BEHAVIOR_BUSINESS);
		$campaign = $this->business->getCampaignCollection()->getItemById($campaignId);

		if ($campaign === null || $businessTrading === null || !$businessTrading->isActive()) { return; }

		$campaignTrading = $businessTrading->bootCampaign($campaign);

		$campaignTrading->install();
	}
}