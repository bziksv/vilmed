<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Dimensions extends Base
{
    use Market\Reference\Concerns\HasMessage;

    protected $unitMap = [
        'mm' => 0.1,
        'cm' => 1,
        'dm' => 10,
        'm' => 100,
    ];

	public function getDefaultParameters()
	{
		return [
			'name' => 'dimensions',
			'value_type' => Market\Type\Manager::TYPE_DIMENSIONS,
			'value_ratio' => 1,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'YM_SIZE',
            ],
        ];
	}

	public function getSettingsDescription(array $context = [])
	{
		return [
			'BITRIX_UNIT' => [
				'TITLE' => self::getMessage('BITRIX_UNIT'),
				'TYPE' => 'enumeration',
				'VALUES' => array_map(static function($unit) {
                    return [
                        'ID' => $unit,
                        'VALUE' => self::getMessage('BITRIX_UNIT_' . mb_strtoupper($unit))
                    ];
                }, array_keys($this->unitMap)),
				'GROUP' => 'DIMENSIONS_BITRIX_UNIT',
			],
		];
	}

    protected function typeSettings(array $tagValue = null, array $siblingsValues = null)
    {
        if (isset($tagValue['SETTINGS']['BITRIX_UNIT'], $this->unitMap[$tagValue['SETTINGS']['BITRIX_UNIT']]))
        {
            return [
                'value_ratio' => $this->unitMap[$tagValue['SETTINGS']['BITRIX_UNIT']],
            ];
        }

        return null;
    }
}