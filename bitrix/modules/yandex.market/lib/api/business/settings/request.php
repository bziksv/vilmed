<?php
namespace Yandex\Market\Api\Business\Settings;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market;
use Yandex\Market\Api\Reference\Transport\Cache;

/** @method Response execute() */
class Request extends Market\Api\Partner\Reference\BusinessRequest
{
	public function getPath()
	{
		return sprintf('/businesses/%s/settings.json', $this->getBusinessId());
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}