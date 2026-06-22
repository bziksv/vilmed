<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Height extends Dimensions
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'height',
			'value_type' => Market\Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
		] + parent::getDefaultParameters();
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'HEIGHT',
            ],
        ];
	}
}