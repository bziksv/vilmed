<?php
namespace Yandex\Market\Api\Campaigns\Model;

use Yandex\Market\Api;
use Yandex\Market\Trading;

class Campaign extends Api\Reference\Model
{
	const PLACEMENT_FBS = Trading\Campaign\Placement::FBS;
	const PLACEMENT_FBY = Trading\Campaign\Placement::FBY;
	const PLACEMENT_DBS = Trading\Campaign\Placement::DBS;

	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function getDomain()
	{
		return (string)$this->requireField('domain');
	}

	public function getBusiness()
	{
		return $this->requireModel('business', Business::class);
	}

	public function getPlacementType()
	{
		return $this->getField('placementType');
	}

	public function getTradingBehavior()
	{
		return Trading\Campaign\Placement::toBehavior($this->getPlacementType());
	}
}