<?php
namespace Yandex\Market\Api\Category\Parameters;

use Bitrix\Main;
use Yandex\Market\Api\Reference\RequestTokenized;
use Yandex\Market\Api\Reference\Transport\Cache;

/** @method Response execute() */
class Request extends RequestTokenized
{
    const CATEGORY_NOT_FOUND = 'CATEGORY_NOT_FOUND';

	protected $categoryId;

    public function getHost()
    {
        return 'api.partner.market.yandex.ru';
    }

    public function getPath()
    {
        return '/category/'. $this->categoryId .'/parameters';
    }

    public function getMethod()
    {
        return Main\Web\HttpClient::HTTP_POST;
    }

	public function setCategoryId($categoryId)
	{
		$this->categoryId = (int)$categoryId;
	}

	public function buildResponse($data)
    {
        return new Response($data);
    }

    protected function createCache()
    {
        return (new Cache())
            ->errorCodes([
                self::CATEGORY_NOT_FOUND,
            ]);
    }
}