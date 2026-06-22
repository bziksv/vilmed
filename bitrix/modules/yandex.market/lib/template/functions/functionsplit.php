<?php
namespace Yandex\Market\Template\Functions;

use Bitrix\Iblock;
use Bitrix\Main;

if (!Main\Loader::includeModule('iblock')) { return; }

/** @noinspection PhpUnused */
class FunctionSplit extends Iblock\Template\Functions\FunctionBase
{
	public function calculate(array $parameters)
	{
		$messages = $this->parametersToArray($parameters);
		$separator = array_pop($messages);

		return explode($separator, implode($separator, $messages));
	}
}