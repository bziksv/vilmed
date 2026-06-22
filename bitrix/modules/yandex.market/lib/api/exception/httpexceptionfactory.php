<?php
namespace Yandex\Market\Api\Exception;

class HttpExceptionFactory
{
	const BAD_REQUEST = 400;
	const UNAUTHORIZED = 401;
	const FORBIDDEN = 403;
	const NOT_FOUND = 404;
	const METHOD_FAILURE = 420;
	const LOCKED = 423;
	const SERVER_ERROR = 500;

	public static function make($httpStatus, $errorMessage, $errorCode = null)
	{
		$httpStatus = (int)$httpStatus;

		if ($httpStatus === self::UNAUTHORIZED || $httpStatus === self::FORBIDDEN)
		{
			return new ForbiddenException($errorMessage, $httpStatus, $errorCode);
		}

		if ($httpStatus === self::METHOD_FAILURE)
		{
			return new MethodFailureException($errorMessage, $httpStatus, $errorCode);
		}

		if ($httpStatus === self::NOT_FOUND)
		{
			return new NotFoundException($errorMessage, $httpStatus, $errorCode);
		}

		if ($httpStatus === self::LOCKED)
		{
			return new LockedException($errorMessage, $httpStatus, $errorCode);
		}

		if ($httpStatus >= self::BAD_REQUEST && $httpStatus < self::BAD_REQUEST + 100)
		{
			return new BadRequestException($errorMessage, $httpStatus, $errorCode);
		}

		if ($httpStatus >= self::SERVER_ERROR && $httpStatus < self::SERVER_ERROR + 100)
		{
			return new ServerErrorException($errorMessage, $httpStatus, $errorCode);
		}

		return new ClientException($errorMessage, $httpStatus, $errorCode);
	}
}