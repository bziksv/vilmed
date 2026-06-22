<?php
namespace Yandex\Market\Api\Business\Warehouses;

use Yandex\Market\Api\Partner\Reference\BusinessRequest;
use Yandex\Market\Api\Reference\Transport\Cache;

/** @method Response execute() */
class Request extends BusinessRequest
{
	public function getPath()
	{
		return sprintf('/businesses/%s/warehouses.json', $this->getBusinessId());
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}