<?php
namespace Yandex\Market\Api\Reference\Transport;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api\Reference\Request;

class Queue
{
	private $queue = [];

	public function add(Middleware $middleware = null)
	{
		if ($middleware !== null)
		{
			$this->queue[] = $middleware;
		}

		return $this;
	}

	public function handle(HttpClient $client, Request $request)
	{
		return $this->next($client, $request);
	}

	public function next(HttpClient $client, Request $request)
	{
		$middleware = array_shift($this->queue);

		if ($middleware !== null)
		{
			return $middleware->handle($client, $request, $this);
		}

		return null;
	}
}