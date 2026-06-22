<?php
namespace Yandex\Market\Trading\Setup;

use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Entity\Reference\Environment;

class CampaignContext extends TradingContext
{
	private $campaign;
	private $urlId;

	public function __construct(
		Business\Model $business,
		Campaign\Model $campaign,
		Environment $environment,
		$siteId,
		$setupId = null,
		$urlId = null
	)
	{
		parent::__construct($business, $environment, $siteId, $setupId);

		$this->campaign = $campaign;
		$this->urlId = $urlId;
	}

	public function getCampaign()
	{
		return $this->campaign;
	}

	public function getUrlId()
	{
		return $this->urlId;
	}
}