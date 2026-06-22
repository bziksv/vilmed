<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Export\Xml\Tag;

/** @var array $arResult */
/** @var array $arParams */

$arResult['DEFAULT_SOURCES'] = [];

foreach ($arParams['GROUPS'] as $group)
{
    /** @var Tag\Base $tag */
    foreach ($group['TAGS'] as $tagId => $tag)
    {
        $arResult['DEFAULT_SOURCES'][$tagId] = (
            isset($arResult['NODE_AVAILABLE_SOURCES'][$tagId][$arResult['RECOMMENDATION_TYPE']])
                ? $arResult['RECOMMENDATION_TYPE']
                : $tag->getDefaultSource($arParams['CONTEXT'])
        );

        foreach ($tag->getAttributes() as $attribute)
        {
            $attributeFullName = $tagId . '.' . $attribute->getId();

            $arResult['DEFAULT_SOURCES'][$attributeFullName] = (
                isset($arResult['NODE_AVAILABLE_SOURCES'][$attributeFullName][$arResult['RECOMMENDATION_TYPE']])
                    ? $arResult['RECOMMENDATION_TYPE']
                    : $attribute->getDefaultSource($arParams['CONTEXT'])
            );
        }
    }
}