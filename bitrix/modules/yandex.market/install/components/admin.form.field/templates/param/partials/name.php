<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

/** @var $arParams array */
/** @var $group array */
/** @var $tag \Yandex\Market\Export\Xml\Tag\Base */
/** @var $tagLevel int */
/** @var $attribute \Yandex\Market\Export\Xml\Attribute\Base */
/** @var $isAttribute bool */
/** @var $isTagRowShown bool */

if ($isAttribute && $isTagRowShown)
{
	$attributeDescription = (string)$attribute->getDescription();

	if ($attributeDescription !== '')
	{
		?>
		<span class="b-icon icon--question size--small indent--right b-tag-tooltip--holder">
			<span class="b-tag-tooltip--content"><?= $attributeDescription ?></span>
		</span><?php
	}

	echo $attribute->getTitle() . '=';
	echo $tagLevel > 0 ? str_repeat('....', $tagLevel) : '';
}
else
{
	$tagNameDisplay = htmlspecialcharsbx($tag->getTitle());
	$tagNameDisplay .= $tagLevel > 0 ? str_repeat('....', $tagLevel) : '';
	$tagDescription = (string)$tag->getDescription();

	if ($arParams['GROUP_FLAT'] === 'Y' && !empty($group['TITLE']))
	{
		$tagNameDisplay = $tagLevel === 0 && count($group['TAGS']) === 1
			? $group['TITLE']
			: ($group['TITLE'] . ': ' . $tagNameDisplay);
	}

	if ($tagDescription !== '')
	{
		?>
		<span class="b-icon icon--question size--small indent--right b-tag-tooltip--holder">
			<span class="b-tag-tooltip--content"><?= $tagDescription ?></span>
		</span><?php
	}

	echo $tagNameDisplay;
}