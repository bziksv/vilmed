<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Xml;

class Plain extends Base
{
    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        if ($value === null || $value === '') { return null; }

        return new Xml\Data\PlainValue($value);
    }
}