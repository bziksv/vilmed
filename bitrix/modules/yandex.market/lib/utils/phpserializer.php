<?php
namespace Yandex\Market\Utils;

class PhpSerializer
{
    public static function encode($value)
    {
        return serialize($value);
    }

    public static function isEncodedArray($value)
    {
        return (
            is_string($value)
            && mb_strpos($value, 'a:') === 0
        );
    }

    public static function decode($value)
    {
        if ((int)PHP_VERSION >= 7)
        {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            return unserialize($value, [ 'allowed_classes' => false ]);
        }

        return unserialize($value);
    }
}