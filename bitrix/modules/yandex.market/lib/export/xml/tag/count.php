<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Count extends Base
{
	use Concerns\HasPackUnitDependency;
	use Concerns\HasPackUnit;

	public function getDefaultParameters()
	{
		return [
			'name' => 'count',
			'value_type' => Market\Type\Manager::TYPE_COUNT,
		];
	}

    public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'QUANTITY',
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