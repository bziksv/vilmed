<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

if (empty($arResult['BOX']) || empty($arResult['BASKET'])) { return; }

foreach ($arResult['BOX'] as &$box)
{
	if (empty($box['ITEMS'])) { continue; }

	foreach ($box['ITEMS'] as &$boxItem)
	{
		if (!isset($arResult['BASKET']['ITEMS'][$boxItem['BASKET_KEY']])) { continue; }

		$basketItem = &$arResult['BASKET']['ITEMS'][$boxItem['BASKET_KEY']];
		$filledCount = 0;

		if (!empty($basketItem['INSTANCES']))
		{
			$instanceIndex = 0;

			foreach ($basketItem['INSTANCES'] as $itemInstances)
			{
				if (!is_array($itemInstances)) { continue; }

				if ($instanceIndex < $boxItem['OFFSET'] || $instanceIndex >= $boxItem['COUNT'] + $boxItem['OFFSET'])
				{
					++$instanceIndex;
					continue;
				}

				$itemFilled = array_filter($itemInstances);

				if (!empty($itemFilled))
				{
					++$filledCount;
				}

				++$instanceIndex;
			}
		}

		$boxItem['IDENTIFIERS_INITIAL_COUNT'] = $filledCount;

		unset($basketItem);
	}
	unset($boxItem);
}
unset($box);
