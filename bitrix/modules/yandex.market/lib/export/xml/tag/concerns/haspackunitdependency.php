<?php
namespace Yandex\Market\Export\Xml\Tag\Concerns;

trait HasPackUnitDependency
{
	protected function copyPricePackUnitSetting(&$tagDescriptionList, array $context = [])
	{
		// search

		$searchGroups = [
			!empty($context['TAG_CHAIN']) ? reset($context['TAG_CHAIN']) : $tagDescriptionList,
		];

		if (!empty($context['SIBLING_TAG_MAP']))
		{
			array_push($searchGroups, ...array_values($context['SIBLING_TAG_MAP']));
		}

		$packRatio = null;

		foreach ($searchGroups as $searchGroup)
		{
			foreach ($searchGroup as $tagDescription)
			{
				if (empty($tagDescription['SETTINGS']['PACK_RATIO'])) { continue; }

				$packRatio = $tagDescription['SETTINGS']['PACK_RATIO'];
				break;
			}

			if ($packRatio !== null) { break; }
		}

		if ($packRatio === null) { return; }

		// write to self settings

		foreach ($tagDescriptionList as &$tagDescription)
		{
			if ($tagDescription['TAG'] !== $this->id) { continue; }
			if (!empty($tagDescription['SETTINGS']['PACK_RATIO'])) { continue; }

			$tagDescription['SETTINGS']['PACK_RATIO'] = $packRatio;
		}
		unset($tagDescription);
	}
}