<?php
namespace Yandex\Market\Catalog\Segment\Price\Tag;

use Yandex\Market\Export\Xml;

class CurrencyId extends Xml\Tag\CurrencyId
{
    public function getSettingsDescription(array $context = [])
    {
        return [];
    }
}