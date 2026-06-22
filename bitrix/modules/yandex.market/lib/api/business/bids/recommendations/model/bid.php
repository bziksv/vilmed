<?php
namespace Yandex\Market\Api\Business\Bids\Recommendations\Model;

use Yandex\Market\Api\Reference\Model;

class Bid extends Model
{
	public function getBid()
	{
		return (int)$this->requireField('bid');
	}

	public function getShowPercent()
	{
		return (int)$this->requireField('showPercent');
	}
}