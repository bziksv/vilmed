<?php

namespace Yandex\Market\Utils;

use Yandex\Market;

class Value
{
	private static $booleanType;

	public static function isEmpty($value)
	{
		if (is_scalar($value))
		{
			$result = (string)$value === '';
		}
		else
		{
			$result = empty($value);
		}

		return $result;
	}

	public static function toBoolean($value)
	{
		if (self::$booleanType === null) { self::$booleanType = new Market\Type\BooleanType(); }

		return (self::$booleanType->sanitize($value) === true);
	}
}