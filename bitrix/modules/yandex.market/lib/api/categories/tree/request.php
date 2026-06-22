<?php
namespace Yandex\Market\Api\Categories\Tree;

use Yandex\Market\Api\Reference\RequestTokenized;
use Yandex\Market\Api\Reference\Transport\Cache;
use Bitrix\Main;

/** @method Response execute() */
class Request extends RequestTokenized
{
    public function getHost()
    {
        return 'api.partner.market.yandex.ru';
    }

    public function getPath()
    {
        return '/categories/tree';
    }

    public function getMethod()
    {
        return Main\Web\HttpClient::HTTP_POST;
    }

	protected function createCache()
	{
		return new Cache();
	}

	public function buildResponse($data)
    {
        return new Response($data);
    }
}