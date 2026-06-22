<?php
namespace Yandex\Market\Template\Functions;

use Bitrix\Iblock;
use Bitrix\Main;

if (!Main\Loader::includeModule('iblock')) { return; }

/** @noinspection PhpUnused */
class FunctionReplace extends Iblock\Template\Functions\FunctionBase
{
	public function calculate(array $parameters)
	{
		$message = array_shift($parameters);
		list($from, $to) = $this->buildReplaces($this->parametersToArray($parameters));

		if (is_array($message))
		{
			foreach ($message as &$messageItem)
			{
				if (!is_scalar($messageItem)) { continue; }

				$messageItem = str_replace($from, $to, $messageItem);
			}
			unset($messageItem);
		}
		else if (is_scalar($message))
		{
			$message = str_replace($from, $to, $message);
		}

		return $message;
	}

	protected function buildReplaces(array $parameters)
	{
		$from = [];
		$lastFrom = null;
		$to = [];
		$index = 0;

		foreach ($parameters as $text)
		{
			$text = is_scalar($text) ? (string)$text : '';

			if ($index % 2 === 0)
			{
				$lastFrom = $text;
			}
			else
			{
				$from[] = $lastFrom;
				$to[] = $text;
			}

			++$index;
		}

		return [ $from, $to ];
	}
}