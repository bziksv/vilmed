<?php
namespace Yandex\Market\Catalog\Segment;

use Yandex\Market\Catalog\Endpoint;
use Yandex\Market\Trading\Business;

interface Factory
{
	/** @return BusinessConfig */
	public function businessConfig(Business\Model $business);

	/** @return CampaignConfig[] */
	public function campaignConfigs(Business\Model $business);

	/** @return Endpoint\Endpoint[] */
	public function endpoints(Business\Model $business, Collection $segmentCollection);
}