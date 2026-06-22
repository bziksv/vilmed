<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Entity;

class Category extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'category',
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Entity\Manager::TYPE_IBLOCK_SECTION,
				'FIELD' => 'NAME',
			],
		];
	}
}