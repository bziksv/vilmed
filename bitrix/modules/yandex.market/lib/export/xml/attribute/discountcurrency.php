<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Error;

class DiscountCurrency extends Base
{
    public function getDefaultParameters()
    {
        return [
            'name' => 'currency',
        ];
    }

    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        if (isset($tagValue['ATTRIBUTES']['unit']) && $tagValue['ATTRIBUTES']['unit'] !== DiscountUnit::UNIT_CURRENCY)
        {
            return new Error\SkipError();
        }

        return parent::sanitize($value, $context, $tagValue, $siblingsValues);
    }
}