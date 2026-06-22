<?php
namespace Yandex\Market\Type;

use Yandex\Market;
use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Export\Xml\Listing;

class PeriodType extends AbstractType
{
	use Concerns\HasMessage;

	const UNIT_HOUR = 'TH';
	const UNIT_DAY = 'D';
	const UNIT_WEEK = 'W';
	const UNIT_MONTH = 'M';
	const UNIT_YEAR = 'Y';

    protected $unitEnum;

	protected $units = [
		self::UNIT_YEAR => 1,
		self::UNIT_MONTH => 12,
		self::UNIT_WEEK => null,
		self::UNIT_DAY => 30,
		self::UNIT_HOUR => 24,
	];
    protected $unitMap = [
        Listing\PeriodUnit::YEAR => self::UNIT_YEAR,
        Listing\PeriodUnit::MONTH => self::UNIT_MONTH,
        Listing\PeriodUnit::WEEK => self::UNIT_WEEK,
        Listing\PeriodUnit::DAY => self::UNIT_DAY,
        Listing\PeriodUnit::HOUR => self::UNIT_HOUR,
    ];
    protected $settings = [
        'value_unit' => self::UNIT_DAY,
    ];

    public function __construct(array $parameters = null)
    {
        parent::__construct($parameters);

        $this->unitEnum = new EnumType(new Listing\PeriodUnit());
    }

    public function type()
    {
        return Manager::TYPE_PERIOD;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if ($this->isPrepared($value))
		{
            $value = mb_strtoupper(trim($value));
            $parts = $this->splitPrepared($value);

            if ($parts === null)
            {
                return new Error\XmlNode(self::getMessage('ERROR_PREPARED_INVALID'), 'PREPARED_INVALID');
            }

            return $this->combine($parts, $value);
		}

        $parts = [];

        foreach ($this->parseText($value) as list($number, $unit))
        {
            if ($number <= 0) { continue; }

            if ($unit === null)
            {
                $unit = $this->setting('value_unit', $settings);

                if ($unit === null)
                {
                    return new Error\XmlNode(self::getMessage('ERROR_UNIT_NOT_DEFINED'), 'UNIT_NOT_DEFINED');
                }
            }

            $parts[$unit] = (float)$number;
        }

        if (empty($parts))
        {
            return new Error\XmlNode(self::getMessage('ERROR_NUMBER_NOT_FOUND'), 'NUMBER_NOT_FOUND');
        }

        $parts = $this->periodPartsToInteger($parts);

        return $this->combine($parts);
    }

	protected function periodPartsToInteger(array $parts)
	{
		$left = 0;
		$result = [];

		foreach ($this->units as $unit => $ratio)
		{
            if ($ratio === null) { continue; }

			$number = isset($parts[$unit]) ? $parts[$unit] : 0;
			$number += $left * $ratio;

			if ($number <= 0) { continue; }

			$integer = (int)$number;
			$left = round($number - $integer, 2);

			$result[$unit] = $integer;
		}

		return $result;
	}

	protected function combine(array $parts, $prepared = null)
	{
        if ($prepared !== null) { return $prepared; }

		$isTimeStarted = false;
		$result = '';

		foreach ($this->units as $unit => $ratio)
		{
			$number = isset($parts[$unit]) ? (int)$parts[$unit] : 0;

			if ($number <= 0) { continue; }

			if ($result === '') { $result .= 'P'; }

			if (!$isTimeStarted && mb_strpos($unit, 'T') === 0)
			{
				$result .= 'T';
				$isTimeStarted = true;
			}

			if ($isTimeStarted)
			{
				$unit = mb_substr($unit, 1);
			}

			$result .= $number . $unit;
		}

		return $result;
	}

	public function isPrepared($value)
	{
		return (
			is_string($value)
			&& mb_stripos(ltrim($value), 'P') === 0
		);
	}

    protected function splitPrepared($value)
    {
        if (preg_match('/^P(?:(?<Y>\d)+Y)?(?:(?<M>\d)+M)?(?:(?<W>\d+)W)?(?:(?<D>\d+)D)?(T(?:(?<TH>\d+)H)?(\d+M)?(\d+S)?)?$/', $value, $matches))
        {
            return array_intersect_key($matches, $this->units);
        }

        return null;
    }

	protected function parseText($value, $limit = null)
	{
		if (is_numeric($value))
		{
			return [
				[ (float)$value, null ],
			];
		}

		$result = [];
		$search = (string)$value;
		$pattern = '/^\s*(?P<base>\d+)(?:[,.](?P<decimal>\d+))?\s*(?:(?P<unit>[^\d\W]+)|$)/u';

		while ($search !== '' && Market\Data\TextString::match($pattern, $search, $matches))
		{
			if (!empty($matches['unit']))
			{
				$unit = $this->toPeriodUnit($this->unitEnum->format($matches['unit']));
				$valid = ($unit !== null);
			}
			else
			{
				$unit = null;
				$valid = empty($result);
			}

			if ($valid)
			{
				$number = !empty($matches['decimal']) ? (float)($matches['base'] . '.' . $matches['decimal']) : (float)$matches['base'];

				$result[] = [ $number, $unit ];

				if ($limit !== null && count($result) >= $limit) { break; }
			}

			$search = mb_substr($search, mb_strlen($matches[0]));
			$search = ltrim($search, '.'); // abbreviations support
		}

		return $result;
	}

    protected function toPeriodUnit($listingUnit)
    {
        if ($listingUnit === null) { return null; }

        return isset($this->unitMap[$listingUnit]) ? $this->unitMap[$listingUnit] : null;
    }

    protected function toListingUnit($periodUnit)
    {
        if ($periodUnit === null) { return null; }

        $listingUnit = array_search($periodUnit, $this->unitMap, true);

        return $listingUnit !== false ? $listingUnit : null;
    }
}