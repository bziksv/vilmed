<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Expiry extends Base
{
	use Market\Reference\Concerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'name' => 'expiry',
			'value_type' => Market\Type\Manager::TYPE_DATEPERIOD,
			'date_format' => 'Y-m-d\TH:i'
		];
	}

    protected function typeSettings(array $tagValue = null, array $siblingsValues = null)
    {
        if (!empty($tagValue['SETTINGS']['UNIT']) && $tagValue['SETTINGS']['UNIT'] !== 'plain')
        {
            return [
                'value_unit' => $tagValue['SETTINGS']['UNIT'],
            ];
        }

        return null;
    }

	public function getSettingsDescription(array $context = [])
	{
		return [
			'UNIT' => [
				'TITLE' => self::getMessage('SETTINGS_UNIT'),
				'DESCRIPTION' => self::getMessage('SETTINGS_UNIT_HELP'),
				'TYPE' => 'enumeration',
				'VALUES' => $this->getUnitEnum(),
			],
		];
	}

	protected function getUnitEnum()
	{
		$result = [];
		$options = [
			Market\Type\PeriodType::UNIT_DAY,
			Market\Type\PeriodType::UNIT_MONTH,
			Market\Type\PeriodType::UNIT_YEAR,
			Market\Type\PeriodType::UNIT_HOUR,
			'plain',
		];

		foreach ($options as $option)
		{
			$result[] = [
				'ID' => $option,
				'VALUE' => self::getMessage('SETTINGS_UNIT_VALUE_' . Market\Data\TextString::toUpper($option)),
			];
		}

		return $result;
	}
}