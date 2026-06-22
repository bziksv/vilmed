<?php
namespace Yandex\Market\Export\Entity\Trading;

use Yandex\Market\Export\Entity;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Template;

class EnvironmentMapper
{
	public static function parseStores($sourceType, $sourceField)
	{
		if (in_array($sourceType, [ Entity\Manager::TYPE_FORMULA, Entity\Manager::TYPE_TEMPLATE ], true))
		{
			if (!Template\Engine::load()) { return []; }

			$template = Template\Engine::compileTemplate($sourceField);
			$partials = [];

			foreach ($template->getSourceSelect() as $templateType => $templateFields)
			{
				foreach ($templateFields as $templateField)
				{
					$partials[] = self::parseStores($templateType, $templateField);
				}
			}

			if (empty($partials)) { return []; }

			return array_unique(array_merge(...$partials));
		}

		if (preg_match('/^AMOUNT_(\d+)$/', $sourceField, $matches))
		{
			return [ (int)$matches[1] ];
		}

		if ($sourceField === 'QUANTITY')
		{
			return [ TradingEntity\Common\Store::PRODUCT_FIELD_QUANTITY ];
		}

		return [];
	}
}