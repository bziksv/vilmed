<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class CardParametersType extends StringType
{
    use Concerns\HasMessage;

    public function type()
    {
        return Manager::TYPE_CARD_PARAMETERS;
    }

    public function sanitize($value, array $context = [], array $settings = null)
    {
        if (!is_array($value))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_ARRAY'), 'NOT_ARRAY');
        }

        if (!isset($value['parameterId']))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_SET_PARAMETER_ID'), 'NOT_SET_PARAMETER_ID');
        }

        if (!is_numeric($value['parameterId']) || (int)$value['parameterId'] <= 0)
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC_PARAMETER_ID'), 'NOT_NUMERIC_PARAMETER_ID');
        }

        if (!isset($value['value']))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_SET_VALUE'), 'NOT_SET_VALUE');
        }

        if (!is_scalar($value['value']))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NOT_SCALAR_VALUE'), 'NOT_SCALAR_VALUE');
        }

        $sanitized = [
            'parameterId' => (int)$value['parameterId'],
            'value' => $value['value'],
        ];

        if (isset($value['valueId']))
        {
            if (!is_numeric($value['valueId']) || (int)$value['valueId'] <= 0)
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC_VALUE_ID'), 'NOT_NUMERIC_VALUE_ID');
            }

            $sanitized['valueId'] = (int)$value['valueId'];
        }

        if (isset($value['unitId']))
        {
            if (!is_numeric($value['unitId']) || (int)$value['unitId'] <= 0)
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC_UNIT_ID'), 'NOT_NUMERIC_UNIT_ID');
            }

            $sanitized['unitId'] = (int)$value['unitId'];
        }

        return $sanitized;
    }
}