<?php
namespace Yandex\Market\Trading\Setup;

use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Entity\Reference\Environment;

class BusinessContext extends TradingContext
{
	private $campaignCollection;

	public function __construct(
		Business\Model $business,
		Campaign\Collection $campaignCollection,
		Environment $environment,
		$siteId,
		$setupId = null
	)
	{
		parent::__construct($business, $environment, $siteId, $setupId);

		$this->campaignCollection = $campaignCollection;
	}

	public function getCampaignCollection()
	{
		return $this->campaignCollection;
	}

	public function makeCampaignContext(Campaign\Model $campaign)
	{
		return new CampaignContext(
			$this->getBusiness(),
			$campaign,
			$this->getEnvironment(),
			$this->getSiteId(),
			$this->getSetupId()
		);
	}
}