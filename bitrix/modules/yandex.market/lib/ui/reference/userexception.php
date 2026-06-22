<?php
namespace Yandex\Market\Ui\Reference;

use Bitrix\Main\SystemException;

class UserException extends SystemException
{
	private $details;
	private $html;

	public function __construct($message, $details = null, $html = true)
	{
		parent::__construct($message);

		$this->details = $details;
		$this->html = $html;
	}

	public function getDetails()
	{
		return $this->details;
	}

	public function needHtml()
	{
		return $this->html;
	}
}

