<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class DimensionsType extends AbstractType
{
    use Concerns\HasMessage;

    protected $settings = [
        'value_ratio' => 1,
    ];

    public function type()
    {
        return Manager::TYPE_DIMENSIONS;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$partials = $this->split($value);

		if ($partials === null)
		{
			return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
		}

        $partials = $this->applyRatio($partials, (float)$this->setting('value_ratio', $settings));

        foreach ($partials as $dimension)
        {
            if ($dimension <= 0)
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_POSITIVE'), 'NOT_POSITIVE');
            }
        }

		return implode('/', $partials);
	}

	protected function split($value)
	{
        if (!preg_match('/^(\d+(?:[.,]\d+)?)\D*([^0-9.]{1,3})(\d+(?:[.,]\d+)?)\D*\2(\d+(?:[.,]\d+)?)/', trim($value), $matches))
        {
            return null;
        }

        $precision = 3;
        $result = [];

        for ($i = 1; $i <= 4; $i++)
        {
            if ($i === 2) { continue; } // is glue

            $match = str_replace(',', '.', $matches[$i]);
            $matchValue = round($match, $precision);

            if ($matchValue === 0.0 && ceil($match) === 1.0) // round and ceil is floated
            {
                $matchValue = 0.1 ** $precision;
            }

            $result[] = $matchValue;
        }

		return $result;
	}

	protected function applyRatio(array $partials, $ratio)
	{
        if ($ratio === 1.0) { return $partials; }

        foreach ($partials as &$value)
        {
            $value *= $ratio;
        }
        unset($value);

		return $partials;
	}
}