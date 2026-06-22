<?php
namespace Yandex\Market\Type;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Error;

class NumberType extends AbstractType
{
    use Concerns\HasMessage;

	const ROUND_FLOOR = 'floor';
	const ROUND_CEIL = 'ceil';

    const SETTING_PRECISION = 'value_precision';
    const SETTING_NEGATIVE = 'value_negative';
    const SETTING_POSITIVE = 'value_positive';
    const SETTING_ROUND = 'value_round';
    const SETTING_RATIO = 'value_ratio';

    protected $settings = [
        self::SETTING_NEGATIVE => false,
        self::SETTING_POSITIVE => false,
        self::SETTING_PRECISION => 2,
        self::SETTING_ROUND => null,
        self::SETTING_RATIO => 1,
    ];

    public function type()
    {
        return Manager::TYPE_NUMBER;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$sanitized = $this->cast($value, $settings);

		if ($sanitized === null)
		{
            return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC', 'NOT_NUMERIC'));
		}

        if ($sanitized < 0 && $this->setting(self::SETTING_NEGATIVE, $settings) === false)
		{
            return new Error\XmlNode(self::getMessage('ERROR_NEGATIVE', 'NEGATIVE'));
		}

        if ($sanitized <= 0.0 && $this->setting(self::SETTING_POSITIVE, $settings) === true)
		{
            return new Error\XmlNode(self::getMessage('ERROR_NON_POSITIVE', 'NON_POSITIVE'));
		}

		return $sanitized;
	}

	protected function cast($value, array $settings = null)
	{
        if (is_numeric($value))
		{
			$number = $value;
		}
        else if (preg_match('/^\s*(\d+)(?:[,.](\d+))?/', $value, $matches))
		{
			$number = !empty($matches[2]) ? (float)($matches[1] . '.' . $matches[2]) : (float)$matches[1];
        }
        else
        {
            return null;
        }

        $number *= (float)$this->setting(self::SETTING_RATIO, $settings);
        $number = $this->round($number, $settings);

		return $number;
	}

	protected function round($value, array $settings = null)
	{
        $rule = $this->setting(self::SETTING_ROUND, $settings);
        $precision = $this->setting(self::SETTING_PRECISION, $settings);

		if ($rule === static::ROUND_FLOOR)
		{
			return $this->floor($value, $precision);
		}

        if ($rule === static::ROUND_CEIL)
		{
			return $this->ceil($value, $precision);
		}

        if ($precision <= 0)
        {
            return (int)round($value);
        }

		return round($value, $precision);
	}

	protected function floor($value, $precision)
	{
		if ($precision <= 0) { return (int)floor($value); }

		$multiplier = 10 ** $precision;

		return floor($value * $multiplier) / $multiplier;
	}

	protected function ceil($value, $precision)
	{
		if ($precision <= 0) { return (int)ceil($value); }

		$multiplier = 10 ** $precision;

		return ceil($value * $multiplier) / $multiplier;
	}
}