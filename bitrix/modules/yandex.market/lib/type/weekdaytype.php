<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;
use Bitrix\Main;

/** @noinspection PhpUnused */
class WeekdayType extends AbstractType
{
	use Concerns\HasMessage;

	protected $variants;
	protected $formatsMap;

    public function type()
    {
        return Manager::TYPE_WEEKDAY;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$value = $this->cast($value);
        $format = 'INTERNATIONAL_FULL';

		if (is_numeric($value))
		{
			$valueInteger = (int)$value;

			if ($valueInteger < 0)
			{
				return new Error\XmlNode(self::getMessage('ERROR_NUMERIC_NEGATIVE'), 'NUMERIC_NEGATIVE');
			}

            if ($valueInteger > 6)
			{
                return new Error\XmlNode(self::getMessage('ERROR_NUMERIC_EXCEED'), 'NUMERIC_EXCEED');
			}

            return $this->getVariant($valueInteger, $format);
		}

        if ($this->isVariantMatch($value, $format))
        {
            return $value;
        }

        $dayNumber = $this->getVariantDayNumber($value);

        if ($dayNumber === null)
		{
            return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
		}

		return $this->getVariant($dayNumber, $format);
	}

	protected function cast($value)
	{
        if (is_numeric($value))
        {
            $value = (int)$value;

            return $value !== 7 ? $value : 0;
        }

        return mb_strtoupper(trim($value));
	}

	protected function getVariant($dayNumber, $format)
	{
		$variants = $this->getVariants($format);

		return $variants[$dayNumber];
	}

	protected function isVariantMatch($sanitizedValue, $format)
	{
		$map = $this->getFormatsMap();

		return isset($map[$format][$sanitizedValue]);
	}

	protected function getVariants($format)
	{
		if (!isset($this->variants[$format]))
		{
			$map = $this->getFormatsMap();

			if (!isset($map[$format]))
			{
				throw new Main\ArgumentException('unknown weekday format ' . $format);
			}

			$this->variants[$format] = array_flip($map[$format]);
		}

		return $this->variants[$format];
	}

	protected function getVariantDayNumber($sanitizedValue)
	{
		$formatsMap = $this->getFormatsMap();
		$result = null;

		foreach ($formatsMap as $map)
		{
			if (isset($map[$sanitizedValue]))
			{
				$result = $map[$sanitizedValue];
				break;
			}
		}

		return $result;
	}

	protected function getFormatsMap()
	{
		if ($this->formatsMap === null)
		{
			$this->formatsMap =
				$this->getInternationalMap()
				+ $this->getLocalMap();
		}

		return $this->formatsMap;
	}

	protected function getInternationalMap()
	{
		$date = new \DateTime();
		$interval = new \DateInterval('P1D');
		$prefix = 'INTERNATIONAL_';
		$formats = $this->getInternationalFormats();
		$formatKeys = array_keys($formats);
		$formatKeysWithPrefix = $this->appendFormatsPrefix($formatKeys, $prefix);
		$result = array_fill_keys($formatKeysWithPrefix, []);

		for ($i = 0; $i <= 6; $i++)
		{
			$weekdayNumber = $date->format('w');

			foreach ($formats as $formatKey => $format)
			{
				$weekdayName = $date->format($format);
				$weekdayName = mb_strtoupper($weekdayName);

				$result[$prefix . $formatKey][$weekdayName] = $weekdayNumber;
			}

			$date->add($interval);
		}

		return $result;
	}

	protected function getInternationalFormats()
	{
		return [
			'SHORT' => 'D',
			'FULL' => 'l',
		];
	}

	protected function getLocalMap()
	{
		$prefix = 'LOCAL_';
		$formats = $this->getLocalFormats();
		$formatsWithPrefix = $this->appendFormatsPrefix($formats, $prefix);
		$result = array_fill_keys($formatsWithPrefix, []);

		for ($weekdayNumber = 0; $weekdayNumber <= 6; $weekdayNumber++)
		{
			foreach ($formats as $format)
			{
				$langKey = sprintf('LOCAL_%s_%s', $weekdayNumber, $format);
				$weekdayName = self::getMessage($langKey, null, '');

				if ($weekdayName === '') { continue; }

				$weekdayName = mb_strtoupper($weekdayName);

				$result[$prefix . $format][$weekdayName] = $weekdayNumber;
			}
		}

		return $result;
	}

	protected function getLocalFormats()
	{
		return [
			'FULL',
			'TWO',
			'THREE',
		];
	}

	protected function appendFormatsPrefix($formats, $prefix)
	{
		$result = [];

		foreach ($formats as $format)
		{
			$result[] = $prefix . $format;
		}

		return $result;
	}
}