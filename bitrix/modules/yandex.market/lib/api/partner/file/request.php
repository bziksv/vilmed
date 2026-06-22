<?php
namespace Yandex\Market\Api\Partner\File;

use Bitrix\Main;
use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;

/** @method Response execute() */
class Request extends Api\Reference\RequestTokenized
{
	protected $path;

	public function getHost()
	{
		return Api\Glossary::MARKET_API_HOST;
	}

	public function getPath()
	{
		Assert::notNull($this->path, 'path');

		return $this->path;
	}

	/** @noinspection UnnecessaryCastingInspection */
	public function setPath($path)
	{
		$uri = new Main\Web\Uri($path);
		$host = (string)$uri->getHost();

		if ($host !== '' && $host !== $this->getHost())
		{
			throw new Main\ArgumentException($host . ' out of range');
		}

		$this->path = $uri->getPathQuery();
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}