<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField\Helper\Attributes;
use Bitrix\Main\Localization\Loc;

/** @var Market\Export\Xml\Tag\Base $tag */
/** @var string $tagInputName */
/** @var string $tagId */
/** @var array $tagValue */
/** @var boolean $isTagPlaceholder */
/** @var boolean $isParentPlaceholder */
/** @var string $parentInputName */
/** @var string $parentBaseId */
/** @var array $tagValues */
/** @var int $tagIndex */
/** @var int $tagLevel */

if (!isset($previousContext)) { $previousContext = []; }

$previousContext[] = [
	$isParentPlaceholder,
	$parentBaseId,
	$parentInputName,
	isset($parentTag) ? $parentTag : null,
	isset($parentValue) ? $parentValue : null,
	isset($childrenFactory) ? $childrenFactory : null,
	$tagIndex,
	$tag,
	$tagValues,
	$tagValue
];

$isParentPlaceholder = $isTagPlaceholder;
$parentBaseId = $tagId . '.';
$parentInputName = $tagInputName . '[CHILDREN]';
$parentTag = $tag;
$parentValue = $tagValue;
$childrenFactory = [];
$tagIndex = 0;

++$tagLevel;

?>
<div class="js-param-tag__child" <?= Attributes::stringify([
	'data-plugin' => 'Field.Param.TagCollection',
	'data-name' => 'CHILDREN',
    'data-factory-element' => '.js-param-tag-collection__factory.level--' . $tagLevel,
	'data-item-element' => '.js-param-tag-collection__item.level--' . $tagLevel,
	'data-item-delete-element' => '.js-param-tag-collection__item-delete.level--' . $tagLevel,
	'data-tag' => $tagId,
]) ?>>
	<?php
	foreach ($parentTag->getChildren() as $tag)
	{
		if ($tag->isDefined()) { continue; }

		$tagValues = [];

		if (!empty($parentValue['CHILDREN']))
		{
			foreach ($parentValue['CHILDREN'] as $childValue)
			{
				if ($tag->getId() === $childValue['XML_TAG'])
				{
					$tagValues[] = $childValue;
				}
			}
		}

		if ($tag->isMultiple() || $tag->isUnion())
		{
			$childrenFactory[$parentBaseId . $tag->getId()] = [
				'TITLE' => $tag->getTitle(),
				'ENABLED' => true,
			];
		}
		else if (!$tag->isRequired() && !$tag->isVisible())
		{
			$childrenFactory[$parentBaseId . $tag->getId()] = [
				'TITLE' => $tag->getTitle(),
				'ENABLED' => empty($tagValues),
			];
		}

		include __DIR__ . '/tag.php';
	}

	if (!empty($childrenFactory))
	{
		$childrenFactoryActive = (count(array_filter($childrenFactory, static function(array $state) { return $state['ENABLED']; })) > 0);

		?>
		<table class="b-param-table__row">
			<tr>
				<td class="b-param-table__cell width--param-label">&nbsp;</td>
				<td class="b-param-table__cell" colspan="3">
                    <span <?= Attributes::stringify([
                        'class' => "b-link target--none level--{$tagLevel} js-param-tag-collection__factory " . ($childrenFactoryActive ? '' : 'is--hidden'),
                        'tabindex' => 0,
                        'data-items' => $childrenFactory,
                    ]) ?>><?= count($childrenFactory) > 1
                    	? Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_ADD_CHILD', [ '#TAG_NAME#' => $parentTag->getTitle() ])
						: reset($childrenFactory)['TITLE']
					?></span>
				</td>
			</tr>
		</table>
		<?php
	}
	?>
</div>
<?php

// restore parent context

list(
	$isParentPlaceholder,
	$parentBaseId,
	$parentInputName,
	$parentTag,
	$parentValue,
	$childrenFactory,
	$tagIndex,
	$tag,
	$tagValues,
	$tagValue
) = array_pop($previousContext);

--$tagLevel;