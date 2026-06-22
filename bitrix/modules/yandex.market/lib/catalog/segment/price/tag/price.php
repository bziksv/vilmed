<?php
namespace Yandex\Market\Catalog\Segment\Price\Tag;

use Yandex\Market\Export\Xml;

class Price extends Xml\Tag\Price
{
	public function getDefaultParameters()
	{
		return parent::getDefaultParameters() + [
			'value_precision' => 0,
		];
	}

	public function getSettingsDescription(array $context = [])
	{
		$settings = parent::getSettingsDescription($context);

		foreach ($settings as $name => &$setting)
		{
			$setting['GROUP'] = $name;
		}
		unset($setting);

		return $settings;
	}
}