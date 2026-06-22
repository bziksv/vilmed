<?php
namespace Yandex\Market\Trading\Settings\Options;

use Bitrix\Main;

class RequiredValueException extends Main\ArgumentException
{
	public function __construct($parameter, $previous = null)
	{
		parent::__construct("Required option {$parameter} not set", $parameter, $previous);
	}
}