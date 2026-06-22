<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Export\Xml\Tag;

/** @var array $arResult */
/** @var array $arParams */

$arResult['TAG_FACTORY_ACTIVE'] = false;
$totalCount = 0;

foreach ($arParams['GROUPS'] as &$group)
{
    $group['TAG_VALUE'] = [];
    $group['TAG_FACTORY'] = [];
    $group['ACTIVE'] = false;

    /** @var Tag\Base $tag */
    foreach ($group['TAGS'] as $tagId => $tag)
    {
        if (mb_strpos($tagId, '.') !== false) { continue; }

        $tagValues = [];

        foreach ((array)$group['VALUE'] as $rowValue)
        {
            if ($tagId === $rowValue['XML_TAG'])
            {
                $tagValues[] = $rowValue;
            }
        }

        if (empty($tagValues) && $tag->isDeprecated()) { continue; }

        if (!$tag->isDeprecated())
        {
            if ($tag->isMultiple() || $tag->isUnion())
            {
                $arResult['TAG_FACTORY_ACTIVE'] = true;
	            $group['TAG_FACTORY'][$tagId] = [
		            'TITLE' => $tag->getTitle(),
		            'ENABLED' => true,
	            ];
            }
            else if (!$tag->isRequired() && !$tag->isVisible())
            {
                if (!empty($tagValues))
                {
                    $group['TAG_FACTORY'][$tagId] = [
	                    'TITLE' => $tag->getTitle(),
	                    'ENABLED' => false,
                    ];
                }
                else
                {
	                $arResult['TAG_FACTORY_ACTIVE'] = true;
                    $group['TAG_FACTORY'][$tagId] = [
	                    'TITLE' => $tag->getTitle(),
	                    'ENABLED' => true,
                    ];
                }
            }
        }

        $group['TAG_VALUE'][$tagId] = $tagValues;

        if (!empty($tagValues) || $tag->isRequired() || $tag->isVisible())
        {
            $group['ACTIVE'] = true;
	        $totalCount += count($tagValues);
        }
    }
}
unset($group);

$arResult['MINIMAL_UI'] = ($totalCount > 50);

