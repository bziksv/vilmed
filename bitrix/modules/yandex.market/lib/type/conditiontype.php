<?php
/** @noinspection PhpDeprecationInspection */
namespace Yandex\Market\Type;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Error;

/** @deprecated */
class ConditionType extends AbstractType
{
    use Concerns\HasMessage;

	const TYPE_NEW = 'new';
	const TYPE_LIKE_NEW = 'likenew';
	const TYPE_USED = 'used';

    public function type()
    {
        return Manager::TYPE_CONDITION;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if ($value === static::TYPE_LIKE_NEW || $value === static::TYPE_USED)
		{
			return $value;
		}

        if ($value === static::TYPE_NEW)
		{
            return new Error\SkipError();
		}

        return new Error\XmlNode(self::getMessage('ERROR_INVALID', [
            '#VALUE#' => is_scalar($value) ? mb_substr($value, 0, 10) : gettype($value),
            '#AVAILABLE#' => implode(', ', [
                static::TYPE_NEW,
                static::TYPE_LIKE_NEW,
                static::TYPE_USED,
            ]),
        ]), 'INVALID');
	}
}