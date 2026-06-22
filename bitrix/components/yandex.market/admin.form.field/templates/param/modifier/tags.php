<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Export\Param;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Export\Xml;

/** @var array $arParams */
/** @var array $arResult */

$arResult['DOCUMENTATION_LINK'] = [];

foreach ($arParams['GROUPS'] as $groupIndex => &$group)
{
    /** @var Param\Format $format */
    $format = $group['FORMAT'];
    $context = $arParams['CONTEXT'];

    Assert::notNull($format, $group['INPUT_NAME'] . '[FORMAT]');
    Assert::isInstanceOf($format, Param\Format::class);

    $reducer = new Xml\TreeReducer(function(Xml\Reference\Node $node, array $parents, array $resultTags) use ($groupIndex, $context) {
        $node->tune($context);

        if (!($node instanceof Xml\Tag\Base) || $node->isDefined()) { return $resultTags; }
        if (empty($parents) && count($node->getAttributes()) === 0) { return $resultTags; }

        array_shift($parents);

        $id = implode('.', array_map(static function(Xml\Tag\Base $parent) { return $parent->getId(); }, $parents));
        $id .= ($id !== '' ? '.' : '') . $node->getId();

        $resultTags[$id] = $node;

        return $resultTags;
    }, true);

    $group['TAGS'] = $reducer->reduce($format->getTag(), []);

	if (empty($arParams['SKIP_DOCUMENTATION']))
	{
        $arResult['DOCUMENTATION_LINK'][] = $format->getDocumentationUrl();
	}
}
unset($group);

$arResult['DOCUMENTATION_LINK'] = array_filter(array_unique($arResult['DOCUMENTATION_LINK']));