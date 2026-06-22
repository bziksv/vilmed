<?php
namespace Yandex\Market\Type\Formatter;

class UpperCase implements Formatter
{
    public function format($value)
    {
        return mb_strtoupper($value);
    }
}