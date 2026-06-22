<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Reference\Concerns as GlobalConcerns;
use Yandex\Market\Result;
use Yandex\Market\Error;
use Yandex\Market\Type;
use Yandex\Market\Data;
use Yandex\Market\Config;
use Yandex\Market\Export\Entity;

class Price extends Base
{
	use GlobalConcerns\HasMessage;
    use Concerns\HasPackUnit;

	public function getDefaultParameters()
	{
		return [
			'name' => 'price',
			'value_type' => Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Entity\Manager::TYPE_CATALOG_PRICE,
                'FIELD' => 'MINIMAL.DISCOUNT_VALUE'
            ],
            [
                'TYPE' => Entity\Manager::TYPE_CATALOG_PRICE,
                'FIELD' => 'MINIMAL.VALUE'
            ],
            [
                'TYPE' => Entity\Manager::TYPE_CATALOG_PRICE,
                'FIELD' => 'BASE.DISCOUNT_VALUE'
            ],
            [
                'TYPE' => Entity\Manager::TYPE_CATALOG_PRICE,
                'FIELD' => 'BASE.VALUE'
            ],
        ];
	}

    public function compareValue($value, array $context = [], Result\XmlValue $nodeValue = null)
	{
        $sanitized = $this->sanitize($value, $context);
        
        if ($sanitized === null || $sanitized instanceof Error\Base) { return null; }
        
        $tagCurrencyId = $nodeValue !== null ? (string)$nodeValue->getTagValue('currencyId') : '';

        if ($tagCurrencyId === '') { return $sanitized; }
        
        $currencyId = (string)Data\Currency::getCurrency($tagCurrencyId);
        $baseCurrencyId = (string)Data\Currency::getBaseCurrency();

		return Data\Currency::convert($sanitized, $currencyId, $baseCurrencyId);
	}

	public function getSettingsDescription(array $context = [])
	{
		$result = [
			'PACK_RATIO' => [
				'TITLE' => self::getMessage('SETTINGS_PACK_RATIO'),
				'DESCRIPTION' => self::getMessage('SETTINGS_PACK_RATIO_HELP'),
				'TYPE' => 'param',
			],
		];

		if (Config::isExpertMode())
		{
			$result['USER_GROUP'] = [
				'TITLE' => self::getMessage('SETTINGS_USER_GROUP'),
				'TYPE' => 'enumeration',
				'VALUES' => $this->getUserGroupEnum(),
			];
		}

		return $result;
	}

	protected function getUserGroupEnum()
	{
		$defaults = Data\UserGroup::getDefaults();
		$defaultsMap = array_flip($defaults);
		$enum = Data\UserGroup::getEnum();

		uasort($enum, static function($aOption, $bOption) use ($defaultsMap) {
			$aSort = (int)isset($defaultsMap[$aOption['ID']]);
			$bSort = (int)isset($defaultsMap[$bOption['ID']]);

			if ($aSort === $bSort) { return 0; }

			return ($aSort > $bSort ? -1 : 1);
		});

		return $enum;
	}
}