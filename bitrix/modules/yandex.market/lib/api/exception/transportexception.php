<?php
namespace Yandex\Market\Api\Exception;

use Bitrix\Main\SystemException;

class TransportException extends SystemException
{
	private $errorCode;

	public function __construct($message = "", $errorCode = null, \Exception $previous = null)
	{
		parent::__construct($message, 0, '', 0, $previous);
		$this->errorCode = $errorCode;
	}

	public function getErrorCode()
	{
		return $this->errorCode;
	}
}