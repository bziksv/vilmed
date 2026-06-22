<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class StepQuantity extends Base
{
	use Concerns\HasPackUnitDependency;
	use Concerns\HasPackUnit;

	public function getDefaultParameters()
	{
		return [
			'name' => 'step-quantity',
			'value_type' => Market\Type\Manager::TYPE_NUMBER,
			'value_precision' => 0,
			'value_positive' => true,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
				'FIELD' => 'MEASURE_RATIO',
			],
		];
	}

	public function extendTagDescriptionList(&$tagDescriptionList, array $context)
	{
		parent::extendTagDescriptionList($tagDescriptionList, $context);
		$this->copyPricePackUnitSetting($tagDescriptionList, $context);
	}

	protected function isPackRatioInverted()
	{
		return true;
	}
}