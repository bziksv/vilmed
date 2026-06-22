<?php
namespace Yandex\Market\Utils;

use Bitrix\Main\Web\Json;

class JsonSerializer
{
    public static function encode($value)
    {
        return Json::encode($value, 0);
    }

    public static function isEncodedObject($value)
    {
        return (is_string($value) && mb_strpos($value, '{') === 0);
    }

    public static function decode($value)
    {
        return Json::decode($value);
    }
}