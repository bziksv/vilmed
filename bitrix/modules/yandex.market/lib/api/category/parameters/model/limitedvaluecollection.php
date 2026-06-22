<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Collection;

class LimitedValueCollection extends Collection
{
    public static function getItemReference()
    {
        return LimitedValue::class;
    }
}