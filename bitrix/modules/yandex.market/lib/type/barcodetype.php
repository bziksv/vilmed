<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class BarcodeType extends StringType
{
	use Concerns\HasMessage;
	
    protected static $availableLengthMap = [
        8 => true,
        12 => true,
        13 => true,
	    14 => true,
    ];

    public function type()
    {
        return Manager::TYPE_BARCODE;
    }

    public function sanitize($value, array $context = [], array $settings = null)
    {
        if (!is_string($value) && !is_numeric($value))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_STRING'), 'NOT_STRING');
        }

        $value = preg_replace('/\D/', '', (string)$value);

        if ($value === '')
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC'), 'NOT_NUMERIC');
        }

        $length = mb_strlen($value);

        if (!isset(static::$availableLengthMap[$length]))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_FOUND_LENGTH_FORMAT'), 'NOT_FOUND_LENGTH_FORMAT');
        }

        return $value;
    }
}