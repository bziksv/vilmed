<?php
namespace Yandex\Market\Api\Business\Bids\Info;

use Yandex\Market\Api;

class Response extends Api\Reference\ResponseWithResult
{
	public function getBids()
	{
		return $this->requireCollection('result.bids', Model\BidCollection::class);
	}

	public function getPaging()
	{
		return $this->anyModel('result.paging', Api\Model\Paging::class);
	}
}