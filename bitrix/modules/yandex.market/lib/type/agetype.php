<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class AgeType extends AbstractType
{
    use Concerns\HasMessage;

	const UNIT_YEAR = 'year';
	const UNIT_MONTH = 'month';

    protected $settings = [
        'value_unit' => self::UNIT_YEAR,
    ];
	protected $unitYearValues = [
		0 => true,
		6 => true,
		12 => true,
		16 => true,
		18 => true,
	];

    public function type()
    {
        return Manager::TYPE_AGE;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if (!is_numeric($value))
		{
            return new Error\XmlNode(self::getMessage('ERROR_NOT_NUMERIC'), 'NOT_NUMERIC');
		}

        $value = (int)$value;

        if ($this->setting('value_unit', $settings, self::UNIT_YEAR) === static::UNIT_MONTH)
        {
            if ($value < 0 || $value > 12)
            {
                return new Error\XmlNode(self::getMessage('ERROR_INVALID_MONTH'), 'INVALID_MONTH');
            }

            return $value;
        }

        if ($value < 0)
        {
            return new Error\XmlNode(self::getMessage('ERROR_NEGATIVE'), 'NEGATIVE');
        }

        if (!isset($this->unitYearValues[$value]))
        {
            return new Error\XmlNode(self::getMessage('ERROR_INVALID_YEAR'), 'INVALID_YEAR');
        }

		return $value;
	}
}