<?php
namespace Yandex\Market\Api\Partner\File;

use Yandex\Market\Psr\Log\LoggerInterface;

class Facade
{
	public static function download($options, $path, LoggerInterface $logger = null)
	{
		$request = new Request($options, $logger);
		$request->setPath($path);

		$response = $request->execute();

		return [ $response->getType(), $response->getContents() ];
	}
}