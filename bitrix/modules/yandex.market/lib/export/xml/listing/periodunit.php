<?php
namespace Yandex\Market\Export\Xml\Listing;

use Yandex\Market\Reference\Concerns;

class PeriodUnit implements Listing
{
    use Concerns\HasMessage;

    const HOUR = 'HOUR';
    const DAY = 'DAY';
    const WEEK = 'WEEK';
    const MONTH = 'MONTH';
    const YEAR = 'YEAR';

    public function values()
    {
        return [
            static::HOUR,
            static::DAY,
            static::WEEK,
            static::MONTH,
            static::YEAR,
        ];
    }

    public function display($value)
    {
        return self::getMessage(mb_strtoupper($value), null, $value);
    }

    public function synonyms($value)
    {
        $message = (string)self::getMessage(mb_strtoupper($value) . '_SYNONYM', null, '');

        if ($message === '') { return []; }

        return explode(',', $message);
    }
}