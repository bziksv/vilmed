<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Vat extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'vat',
			'value_type' => Market\Type\Manager::TYPE_VAT,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return array_merge([
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'VAT',
            ],
        ], parent::getSourceRecommendation($context));
	}
}