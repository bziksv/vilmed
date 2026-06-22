<?php
namespace Yandex\Market\Reference\Storage\Field;

use Bitrix\Main\Web\Json;

class JsonSerializer extends Serializer
{
	public static function serialize($value)
	{
        if ($value === null) { return ''; }

		return Json::encode($value, 0);
	}

	public static function unserialize($value)
	{
        if (!is_string($value)) { return $value; }
        if ($value === '') { return null; }

        return Json::decode($value);
	}
}