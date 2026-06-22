<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Model;

class ValueRestriction extends Model
{
    public function getLimitingParameterId()
    {
        return (int)$this->requireField('limitingParameterId');
    }

    public function getLimitedValues()
    {
        return $this->getCollection('limitedValues', LimitedValueCollection::class);
    }
}