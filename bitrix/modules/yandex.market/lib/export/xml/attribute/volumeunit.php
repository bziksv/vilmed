<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;

class VolumeUnit extends Base
{
    use Concerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'id' => 'volume_unit',
			'name' => 'unit',
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return [
			[
				'TYPE' => Entity\Manager::TYPE_TEXT,
				'VALUE' => self::getMessage('RECOMMENDATION_MILLILITER')
			],
			[
				'TYPE' => Entity\Manager::TYPE_TEXT,
				'VALUE' => self::getMessage('RECOMMENDATION_LITER')
			]
		];
	}
}