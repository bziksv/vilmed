<?php
namespace Yandex\Market\Catalog\Segment\Offer\Listing;

use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;

class AgeUnit implements Xml\Listing\Listing
{
    use Concerns\HasMessage;

    const YEAR = 'YEAR';
    const MONTH = 'MONTH';

    public function values()
    {
        return [
            static::YEAR,
            static::MONTH,
        ];
    }

    public function display($value)
    {
        return self::getMessage(mb_strtoupper($value), null, $value);
    }

    public function synonyms($value)
    {
        return [];
    }
}