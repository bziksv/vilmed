<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class CategoryType extends AbstractType
{
    use Concerns\HasMessage;

    public function type()
    {
        return Manager::TYPE_CATEGORY;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$value = (int)$value;

		if ($value <= 0)
		{
            return new Error\XmlNode(self::getMessage('ERROR_ID'), 'ERROR_ID');
		}

		return $value;
	}
}