<?php
namespace Yandex\Market\Catalog\Card\Tag;

use Yandex\Market\Export\Xml\Attribute;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;

class NameAttribute extends Attribute\ParamName
{
    use Concerns\HasMessage;

    public function preselect(array $context)
    {
        $result = [];

        if (!empty($context['OFFER_PROPERTY_ID']))
        {
            $result[] = [
                'TYPE' => Entity\Manager::TYPE_TEXT,
                'VALUE' => self::getMessage('OFFER_PROPERTY_ID'),
            ];
        }

        return $result;
    }
}