<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Export\Entity;
use Yandex\Market\Type;

class GroupId extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'group_id',
			'value_type' => Type\Manager::TYPE_NUMBER,
		];
	}

	public function tune(array $context)
	{
		if (empty($context['HAS_OFFER'])) { return; }

		$this->isVisible = true;
	}

	public function getSourceRecommendation(array $context = [])
	{
		if (empty($context['OFFER_PROPERTY_ID'])) { return []; }

		return [
			[
				'TYPE' => Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY,
				'FIELD' => $context['OFFER_PROPERTY_ID'],
			],
		];
	}
}