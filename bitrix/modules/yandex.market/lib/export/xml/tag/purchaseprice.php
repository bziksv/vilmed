<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class PurchasePrice extends Price
{
	use Concerns\HasPackUnitDependency;

	public function getDefaultParameters()
	{
		return [ 'name' => 'purchase_price' ] + parent::getDefaultParameters();
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
				'FIELD' => 'PURCHASING_PRICE_RUR'
			],
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
				'FIELD' => 'PURCHASING_PRICE'
			],
		];
	}

	public function extendTagDescriptionList(&$tagDescriptionList, array $context)
	{
		parent::extendTagDescriptionList($tagDescriptionList, $context);
		$this->copyPricePackUnitSetting($tagDescriptionList, $context);
	}

	public function getSettingsDescription(array $context = [])
	{
		return [];
	}
}