<?php
namespace Yandex\Market\Catalog\Endpoint;

interface DriverWithCampaignGroup extends Driver
{
	/** @return int[] */
	public function campaignGroup();
}