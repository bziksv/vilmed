<?php
namespace Yandex\Market\Data\Run;

class PauseException extends \Exception
{
	private $stepName;
	private $offset;
	private $timeout;

	public function __construct($stepName, $offset, $timeout, $message = "", $code = 0, $previous = null)
	{
		parent::__construct($message, $code, $previous);

		$this->stepName = $stepName;
		$this->offset = (string)$offset;
		$this->timeout = (int)$timeout;
	}

	public function getStep()
	{
		return $this->stepName;
	}

	public function getOffset()
	{
		return $this->offset;
	}

	public function getTimeout()
	{
		return $this->timeout;
	}
}