<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Type;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;

class Weight extends Base
{
    use Concerns\HasMessage;

    protected $unitMap = [
        'gram' => 0.001,
        'kilogram' => 1,
        'centner' => 100,
        'ton' => 1000,
        'milligram' => 0.000001
    ];

	public function getDefaultParameters()
	{
		return [
			'name' => 'weight',
			'value_type' => Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
			'value_precision' => 3,
			'value_ratio' => 1,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'WEIGHT',
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
                }, array_keys($this->unitMap))
			],
		];
	}
}