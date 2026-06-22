<?php
namespace Yandex\Market\Api\Exception;

class HttpException extends TransportException
{
	private $httpCode;

	public function __construct($message = "", $httpCode = 0, $errorCode = null, \Exception $previous = null)
	{
		if ($errorCode === null) { $errorCode = 'STATUS_' . $httpCode; }

		parent::__construct($message, $errorCode, $previous);
		$this->httpCode = (int)$httpCode;
	}

	public function getHttpCode()
	{
		return $this->httpCode;
	}
}