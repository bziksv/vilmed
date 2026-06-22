<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Export\Xml\Listing;
use Yandex\Market\Type;

class DiscountUnit extends Base
{
    const UNIT_CURRENCY = 'currency';
    const UNIT_PERCENT = 'percent';

    public function getDefaultParameters()
    {
        return [
            'name' => 'unit',
            'value_type' => Type\Manager::TYPE_ENUM,
            'value_listing' => new Listing\Custom([
                self::UNIT_CURRENCY,
                self::UNIT_PERCENT,
            ]),
        ];
    }
}