<?php
namespace Yandex\Market\Trading\Service\Reference;

use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Service\Common;

abstract class CampaignFactory
{
	protected $provider;

	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
	}

	/** @return Common\Provider */
	abstract public function getProvider(Campaign\Model $campaign);
}