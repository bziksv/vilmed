<?php
namespace Yandex\Market\Type;

class TimePeriodType extends PeriodType
{
    protected function combine(array $parts, $prepared = null)
    {
        $result = [];

        foreach ($parts as $periodUnit => $value)
        {
            $value = (int)$value;
            $unit = $this->toListingUnit($periodUnit);

            if ($value <= 0 || $unit === null) { continue; }

            $result[] = [ $value, $unit ];
        }

        return $result;
    }
}