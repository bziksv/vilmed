<?php
namespace Yandex\Market\Api\Reference\Validator;

use Yandex\Market\Api;

class HttpError implements Validator
{
	public function check($data, $httpStatus)
	{
		$status = (int)$httpStatus;

		if ($status === 200) { return; }

		// prevent switch off without error code
		if (in_array($status, [Api\Exception\HttpExceptionFactory::FORBIDDEN, Api\Exception\HttpExceptionFactory::UNAUTHORIZED], true))
		{
			throw new Api\Exception\HttpException("HTTP {$status}", $status);
		}

		throw Api\Exception\HttpExceptionFactory::make($status, "HTTP {$status}");
	}
}