<?php
namespace Yandex\Market\Api\Partner\Outlets;

use Yandex\Market;

class Response extends Market\Api\Partner\Reference\Response
{
	public function getOutletCollection()
	{
		$collection = $this->requireCollection('outlets', Market\Api\Model\OutletCollection::class);
		$collection->setPaging($this->getPaging());

		return $collection;
	}

	public function getPaging()
	{
		return $this->anyModel('paging', Market\Api\Model\Paging::class);
	}
}
