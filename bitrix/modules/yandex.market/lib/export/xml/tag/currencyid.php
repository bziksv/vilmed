<?php

namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;
use Bitrix\Main;

class CurrencyId extends Base
{
	use Market\Reference\Concerns\HasLang;

	const BASE_CURRENCY_DEFAULT = 'YM_DEFAULT';

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public function getDefaultParameters()
	{
		return [
			'name' => 'currencyId',
			'value_type' => Market\Type\Manager::TYPE_CURRENCY
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		if ($context['HAS_CATALOG'])
		{
			return array_merge(
				$this->getSiteCurrencyRecommendation(),
				$this->getCatalogPriceRecommendation()
			);
		}

		return $this->getTextRecommendation();
	}

	protected function getSiteCurrencyRecommendation()
	{
        $currency = Market\Data\Currency::getCurrency('RUR') ?: Market\Data\Currency::getBaseCurrency();

        if (!$currency) { return []; }

		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CURRENCY,
                'FIELD' => $currency,
            ],
        ];
	}

	protected function getCatalogPriceRecommendation()
	{
		return [
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRICE,
				'FIELD' => 'MINIMAL.CURRENCY'
			],
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRICE,
				'FIELD' => 'OPTIMAL.CURRENCY'
			],
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRICE,
				'FIELD' => 'BASE.CURRENCY'
			],
		];
	}

	protected function getTextRecommendation()
	{
		return [
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_TEXT,
				'VALUE' => 'RUR'
			]
		];
	}

	public function getSettingsDescription(array $context = [])
	{
		$result = [];

		if (Market\Config::isExpertMode())
		{
			$result['BASE_CURRENCY'] = [
				'TITLE' => static::getLang($this->getLangKey() . '_SETTINGS_BASE_CURRENCY'),
				'TYPE' => 'enumeration',
				'VALUES' => $this->getBaseCurrencyEnum($context),
			];
		}

		return $result;
	}

	protected function getBaseCurrencyEnum(array $context)
	{
		$defaults = $this->getBaseCurrencyDefaults();
		$currencyEnum = $this->getBaseCurrencySourceEnum($context);

		if (empty($currencyEnum))
		{
			$currencyEnum = $this->getBaseCurrencyTypeEnum();
		}

		return array_merge($defaults, $currencyEnum);
	}

	protected function getBaseCurrencyDefaults()
	{
		return [
			[
				'ID' => static::BASE_CURRENCY_DEFAULT,
				'VALUE' => static::getLang($this->getLangKey() . '_SETTINGS_BASE_CURRENCY_DEFAULT'),
			],
		];
	}

	protected function getBaseCurrencySourceEnum(array $context)
	{
		$currencySource = $this->getCurrencySource();
		$currencyType = $this->getCurrencyType();
		$result = [];

		foreach ($currencySource->getFields($context) as $currencyField)
		{
			if (
				!empty($currencyField['SELECTABLE'])
				&& $currencyField['TYPE'] === Market\Export\Entity\Data::TYPE_CURRENCY_CONVERT
				&& $currencyType->isBase($currencyField['ID'])
			)
			{
				$result[] = [
					'ID' => $currencyField['ID'],
					'VALUE' => $currencyField['VALUE'],
				];
			}
		}

		return $result;
	}

	protected function getBaseCurrencyTypeEnum()
	{
		$result = [];

		foreach ($this->getCurrencyType()->getBaseList() as $currency => $dummy)
		{
			$result[] = [
				'ID' => $currency,
				'VALUE' => $currency,
			];
		}

		return $result;
	}

	protected function getCurrencySource()
	{
		return Market\Export\Entity\Manager::getSource(Market\Export\Entity\Manager::TYPE_CURRENCY);
	}

	protected function getCurrencyType()
	{
		if (!($this->type instanceof Market\Type\CurrencyType))
		{
			throw new Main\SystemException(sprintf(
				'%s tag supports only currency value type',
				$this->name
			));
		}

		return $this->type;
	}
}