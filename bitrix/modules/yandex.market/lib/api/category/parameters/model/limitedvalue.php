<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Model;

class LimitedValue extends Model
{
    public function getLimitingOptionValueId()
    {
        return (int)$this->requireField('limitingOptionValueId');
    }

    /** @return int[] */
    public function getLimitedValues()
    {
        return (array)$this->getField('optionValueIds');
    }
}