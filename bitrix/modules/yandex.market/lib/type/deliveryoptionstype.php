<?php
/**
 * @noinspection PhpDeprecationInspection
 * @noinspection PhpUnused
 */
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @deprecated */
class DeliveryOptionsType extends AbstractType
{
    use Concerns\HasMessage;

    public function type()
    {
        return Manager::TYPE_DELIVERY_OPTIONS;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if (!is_array($value))
		{
            return new Error\XmlNode(self::getMessage('ERROR_NOT_ARRAY'), 'NOT_ARRAY');
		}

        foreach ($value as $option)
        {
            if (!isset($option['COST']) || $option['COST'] === '')
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_SET_COST'), 'NOT_SET_COST');
            }

            if (!isset($option['DAYS']) || $option['DAYS'] === '')
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_SET_DAYS'), 'NOT_SET_DAYS');
            }
        }

		return $value;
	}
}