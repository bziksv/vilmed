<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class TnVedCodeType extends AbstractType
{
	use Concerns\HasMessage;

    public function type()
    {
        return Manager::TYPE_TN_VED_CODE;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
        if (!is_string($value) && !is_numeric($value)) { return null; }

		$value = preg_replace('/\D/', '', (string)$value);

		if ($value === '')
		{
			return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC'), 'NOT_NUMERIC');
		}

        $length = mb_strlen($value);

        if ($length !== 10 && $length !== 14)
		{
            return new Error\XmlNode(self::getMessage('ERROR_LENGTH_NOT_MATCH'), 'LENGTH_NOT_MATCH');
		}

		return $value;
	}
}