<?php
namespace Yandex\Market\Catalog\Segment\Price\Tag;

use Yandex\Market\Export\Xml;
use Yandex\Market\Type;

class AdditionalExpenses extends Xml\Tag\Base
{
	use Xml\Tag\Concerns\HasPackUnit;
	use Xml\Tag\Concerns\HasPackUnitDependency;

	public function getDefaultParameters()
	{
		return [
			'name' => 'additionalExpenses',
			'value_type' => Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
			'value_precision' => 0,
		];
	}

	public function extendTagDescriptionList(&$tagDescriptionList, array $context)
	{
		parent::extendTagDescriptionList($tagDescriptionList, $context);
		$this->copyPricePackUnitSetting($tagDescriptionList, $context);
	}
}