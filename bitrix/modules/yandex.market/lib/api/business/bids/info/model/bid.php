<?php
namespace Yandex\Market\Api\Business\Bids\Info\Model;

use Yandex\Market\Api\Reference\Model;

class Bid extends Model
{
	public function getSku()
	{
		return (string)$this->requireField('sku');
	}

	public function getBid()
	{
		return (int)$this->requireField('bid');
	}
}