<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField\Helper\Attributes;
use Bitrix\Main\Localization\Loc;

/** @var Market\Export\Xml\Tag\Base $tag */
/** @var string $tagInputName */
/** @var string $tagName */
/** @var string $tagId */
/** @var array $tagValue */
/** @var boolean $isTagPlaceholder */
/** @var int $attributeIndex */

$attributesFactory = [];
$attributesFactoryActive = false;

foreach ($tag->getAttributes() as $attribute)
{
	$isDefined = $attribute->isDefined();

	if ($isDefined && !$attribute->isVisible()) { continue; } /* предопределенный аттрибут */

	$attributeInputName = $tagInputName . '[PARAM_VALUE][' . $attributeIndex . ']';
	$attributeValue = null;
	$attributeId = $attribute->getId();
	$attributeName = $attribute->getName();
	$attributeType = Market\Export\ParamValue\Table::XML_TYPE_ATTRIBUTE;
	$attributeValueType = $attribute->getValueType();
	$isAttribute = true;
	$isRequired = $attribute->isRequired();
	$isAttributePlaceholder = false;

	if (!$isTagPlaceholder && !empty($tagValue['PARAM_VALUE']))
	{
		foreach ($tagValue['PARAM_VALUE'] as $paramValue)
		{
			if (
				$paramValue['XML_TYPE'] === $attributeType
				&& $paramValue['XML_ATTRIBUTE_NAME'] === $attributeId
			)
			{
				$attributeValue = $paramValue;
				break;
			}
		}
	}

	if ($attributeValue === null)
	{
		$attributeValue = [];
		$isAttributePlaceholder = (!$attribute->isRequired() && !$attribute->isVisible());
	}

	if ($isDefined)
	{
		$definedSource = $attribute->getDefinedSource();

		$attributeValue['SOURCE_TYPE'] = $definedSource['TYPE'];
		$attributeValue['SOURCE_FIELD'] = (
			(!empty($arResult['SOURCE_TYPE_ENUM'][$definedSource['TYPE']]['VARIABLE']))
				? $definedSource['VALUE']
				: $definedSource['FIELD']
		);
	}

	include __DIR__ . '/value.php';

	if (!$attribute->isRequired() && !$attribute->isVisible())
	{
		$attributesFactory["{$tagId}.{$attributeId}"] = [
			'ENABLED' => $isAttributePlaceholder,
			'TITLE' => $attribute->getTitle(),
		];
		$attributesFactoryActive = ($attributesFactoryActive || $isAttributePlaceholder);
	}

	if (!$isAttributePlaceholder)
	{
		$attributeIndex++;
	}
}

if (!empty($attributesFactory))
{
	?>
	<tr class=" ">
		<td class="b-param-table__cell width--param-label">&nbsp;</td>
		<td class="b-param-table__cell" colspan="3">
            <span <?= Attributes::stringify([
                'class' => 'b-link target--none js-param-node-collection__attribute-factory ' . ($attributesFactoryActive ? '' : 'is--hidden'),
                'tabindex' => 0,
                'data-items' => $attributesFactory,
            ]) ?>><?= count($attributesFactory) > 1
            	? Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_ADD_ATTRIBUTE', [ '#TAG_NAME#' => $tagName ])
				: reset($attributesFactory)['TITLE']
			?></span>
		</td>
	</tr>
	<?php
}