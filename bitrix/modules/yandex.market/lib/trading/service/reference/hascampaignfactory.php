<?php
namespace Yandex\Market\Trading\Service\Reference;

interface HasCampaignFactory
{
	/** @return CampaignFactory */
	public function getCampaignFactory();
}