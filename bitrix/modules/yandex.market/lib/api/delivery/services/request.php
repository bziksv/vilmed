<?php
namespace Yandex\Market\Api\Delivery\Services;

use Yandex\Market\Api\Reference\RequestTokenized;
use Yandex\Market\Api\Reference\Transport\Cache;

/** @method Response execute() */
class Request extends RequestTokenized
{
	protected $orderId;

	public function getHost()
	{
		return 'api.partner.market.yandex.ru';
	}

	public function getPath()
	{
		return '/v2/delivery/services.json';
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}

	protected function createCache()
	{
		return new Cache();
	}
}