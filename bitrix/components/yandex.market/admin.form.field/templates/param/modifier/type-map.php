<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;

/** @var array $arParams */
/** @var array $arResult */

$arResult['TYPE_MAP'] = [];
$arResult['TYPE_MAP_JS'] = [];
$arResult['NODE_AVAILABLE_SOURCES'] = [];

$variableSourceTypes = array_fill_keys(
    array_keys(array_filter($arResult['SOURCE_TYPE_ENUM'], static function(array $type) { return !empty($type['VARIABLE']); })),
    true
);
$templateSourceTypes = array_fill_keys(
	array_keys(array_filter($arResult['SOURCE_TYPE_ENUM'], static function(array $type) { return !empty($type['TEMPLATE']); })),
    true
);

foreach ($arParams['GROUPS'] as $group)
{
    /** @var Market\Export\Xml\Tag\Base $tag */
    foreach ($group['TAGS'] as $tagId => $tag)
    {
        $nodes = array_merge([ $tag ], $tag->getAttributes());

        foreach ($nodes as $node)
        {
            $nodeFullType = ($node === $tag ? $tagId : $tagId . '.' . $node->getId());
            $nodeSources = [];
            $valueType = $node->getValueType();
            $typeMap = null;

            if (!isset($arResult['TYPE_MAP'][$valueType]))
            {
                $typeList = Market\Export\Entity\Data::getDataTypes($valueType);
                $typeMap = array_flip($typeList);

                $arResult['TYPE_MAP_JS'][$valueType] = $typeList;
                $arResult['TYPE_MAP'][$valueType] = $typeMap;
            }
            else
            {
                $typeMap = $arResult['TYPE_MAP'][$valueType];
            }

            if (isset($typeMap[Market\Export\Entity\Data::TYPE_STRING]))
            {
                $nodeSources += $variableSourceTypes;
                $nodeSources += $templateSourceTypes;
            }

            foreach ($arResult['SOURCE_FIELD_ENUM'] as $fieldEnum)
            {
                if (
                    !isset($nodeSources[$fieldEnum['SOURCE']])
                    && isset($typeMap[$fieldEnum['TYPE']])
                    && (!isset($fieldEnum['TAG']) || in_array($nodeFullType, $fieldEnum['TAG'], true))
                )
                {
                    $nodeSources[$fieldEnum['SOURCE']] = true;
                }
            }

            $arResult['NODE_AVAILABLE_SOURCES'][$nodeFullType] = $nodeSources;
        }
    }
}