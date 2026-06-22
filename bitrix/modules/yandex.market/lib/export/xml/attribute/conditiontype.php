<?php

namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Type as MarketType;
use Yandex\Market\Export\Xml;
use Yandex\Market\Ui\UserField;

class ConditionType extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'type',
			'value_type' => MarketType\Manager::TYPE_ENUM,
			'value_listing' => new Xml\Listing\ConditionType(),
			'value_skip' => [
				Xml\Listing\ConditionType::NEW_TYPE,
			],
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		$userTypes = [
			UserField\ConditionType\Property::USER_TYPE,
		];

		return Xml\Routine\Recommendation\Property::userTypeValue($userTypes, $context);
	}
}