<?php
namespace Yandex\Market\Api\Reference\Transport;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;
use Yandex\Market\Api\Reference\Request;

class Locker implements Middleware
{
	private $locker;

	public function __construct(Api\Locker $locker)
	{
		$this->locker = $locker;
	}

	public function handle(HttpClient $client, Request $request, Queue $queue)
	{
		try
		{
			$this->locker->lock();
			$result = $queue->next($client, $request);
			$this->locker->release();

			return $result;
		}
		catch (\Exception $exception)
		{
			$this->locker->release();
			throw $exception;
		}
		/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
		catch (\Throwable $exception)
		{
			$this->locker->release();
			throw $exception;
		}
	}
}