<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Entity\Market\Property\Source;
use Yandex\Market\Export\Entity\Manager;
use Yandex\Market\Type;

class MarketCategoryId extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'market_category_id',
			'value_type' => Type\Manager::TYPE_NUMBER,
			'value_positive' => true,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Manager::TYPE_MARKET_PROPERTY,
				'FIELD' => Source::FIELD_CATEGORY_ID,
			],
		];
	}
}