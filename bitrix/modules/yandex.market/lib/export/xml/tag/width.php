<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class Width extends Dimensions
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'width',
			'value_type' => Market\Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
		] + parent::getDefaultParameters();
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => 'WIDTH',
            ],
        ];
	}
}