<?php
namespace Yandex\Market\Component\Business;

use Yandex\Market\Export\Xml\Tag;
use Yandex\Market\Trading;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Utils\ArrayHelper;

class OptionsMigrator
{
	private $changes = [];

	public function compile(Trading\Setup\Collection $tradingCollection, Trading\Setup\Model $excludeTrading = null)
	{
		$exportSettings = [];
		$significantCollection = $tradingCollection->filterActive();

		if ($significantCollection->count() === 0) { $significantCollection = $tradingCollection; }

		/** @var Trading\Setup\Model $trading */
		foreach ($significantCollection as $trading)
		{
			if ($excludeTrading === $trading) { continue; }

			$tradingSettings = $trading->getSettings()->getValues();
			$selfSettings = array_diff_key($tradingSettings, [
				'API_KEY' => true,
				'OAUTH_TOKEN' => true,
				'OAUTH_CLIENT_ID' => true,
				'OAUTH_CLIENT_PASSWORD' => true,
				'PRODUCT_SKU_FIELD' => true,
				'PRODUCT_USE_SKU_PREFIX' => true,
				'PRODUCT_SKU_PREFIX' => true,
				'PRODUCT_SKU_ADV_PREFIX' => true,
			]);

			if (!empty($tradingSettings['API_KEY']) && !isset($exportSettings['API_KEY']))
			{
				$exportSettings['API_KEY'] = $tradingSettings['API_KEY'];
			}

			if (!isset($exportSettings['PRODUCT_USE_SKU_PREFIX']))
			{
				if (
					!isset($tradingSettings['PRODUCT_USE_SKU_PREFIX'], $tradingSettings['PRODUCT_SKU_PREFIX'])
					&& isset($tradingSettings['PRODUCT_SKU_ADV_PREFIX']) && (string)$tradingSettings['PRODUCT_SKU_ADV_PREFIX'] === UserField\BooleanType::VALUE_Y
				)
				{
					$exportSettings['PRODUCT_USE_SKU_PREFIX'] = UserField\BooleanType::VALUE_Y;
					$exportSettings['PRODUCT_SKU_PREFIX'] = Tag\Offer::ADV_PREFIX;
				}
				else
				{
					$exportSettings['PRODUCT_USE_SKU_PREFIX'] = $tradingSettings['PRODUCT_USE_SKU_PREFIX'];
					$exportSettings['PRODUCT_SKU_PREFIX'] = $tradingSettings['PRODUCT_SKU_PREFIX'];
				}
			}

			if (!isset($exportSettings['API_KEY'], $exportSettings['OAUTH_TOKEN']) && !empty($tradingSettings['OAUTH_TOKEN']))
			{
				$exportSettings['OAUTH_TOKEN'] = $tradingSettings['OAUTH_TOKEN'];
				$exportSettings['OAUTH_CLIENT_ID'] = $tradingSettings['OAUTH_CLIENT_ID'];
				$exportSettings['OAUTH_CLIENT_PASSWORD'] = $tradingSettings['OAUTH_CLIENT_PASSWORD'];
			}

			if (empty($tradingSettings['PRODUCT_SKU_FIELD']) || !is_array($tradingSettings['PRODUCT_SKU_FIELD']))
			{
				$exportSettings['PRODUCT_SKU_FIELD'] = [];
			}
			else if (!isset($exportSettings['PRODUCT_SKU_FIELD']))
			{
				$exportSettings['PRODUCT_SKU_FIELD'] = $tradingSettings['PRODUCT_SKU_FIELD'];
			}
			else if (!empty($exportSettings['SKU_FIELD']))
			{
				$exportSettings['PRODUCT_SKU_FIELD'] = $this->mergeSkuFieldMap($exportSettings['PRODUCT_SKU_FIELD'], $tradingSettings['PRODUCT_SKU_FIELD']);
			}

			if (count($selfSettings) !== count($tradingSettings))
			{
				$this->pushChanges($trading, $selfSettings);
			}
		}

		return $exportSettings;
	}

	public function commit()
	{
		/** @var Trading\Setup\Model $trading */
		/** @var array $leftSettings */
		foreach ($this->changes as list($trading, $settings))
		{
			$trading->setField('SETTINGS', $this->settingsToRows($settings));
			$trading->save();
		}
	}

	private function mergeSkuFieldMap(array $aSkuMap, array $bSkuMap)
	{
		$aSkuMap = ArrayHelper::columnsHashKey($aSkuMap, [ 'IBLOCK', 'FIELD' ]);
		$bSkuMap = ArrayHelper::columnsHashKey($bSkuMap, [ 'IBLOCK', 'FIELD' ]);

		return array_values($aSkuMap + $bSkuMap);
	}

	private function pushChanges(Trading\Setup\Model $trading, array $leftSettings)
	{
		$this->changes[] = [ $trading, $leftSettings ];
	}

	private function settingsToRows(array $values)
	{
		return array_map(
			static function($key, $value) { return [ 'NAME' => $key, 'VALUE' => $value ]; },
			array_keys($values),
			array_values($values)
		);
	}
}