<?php
namespace Yandex\Market\Api\Campaigns;

use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
	public function getCampaigns()
	{
		return $this->requireCollection('campaigns', Model\CampaignCollection::class);
	}

	public function getPager()
	{
		return $this->anyModel('pager', Market\Api\Model\Pager::class);
	}
}