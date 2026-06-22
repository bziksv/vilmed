<?php
namespace Yandex\Market\Catalog\Segment\Offer\Listing;

use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;

class Type implements Xml\Listing\Listing
{
    use Concerns\HasMessage;

    const DEFAULT_TYPE = 'DEFAULT';
    const MEDICINE = 'MEDICINE';
    const BOOK = 'BOOK';
    const AUDIOBOOK = 'AUDIOBOOK';
    const ARTIST_TITLE = 'ARTIST_TITLE';
    const ON_DEMAND = 'ON_DEMAND';
    const ALCOHOL = 'ALCOHOL';

    public function values()
    {
        return [
            static::DEFAULT_TYPE,
            static::MEDICINE,
            static::BOOK,
            static::AUDIOBOOK,
            static::ARTIST_TITLE,
            static::ON_DEMAND,
            static::ALCOHOL,
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