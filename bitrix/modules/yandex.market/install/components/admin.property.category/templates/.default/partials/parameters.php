<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\Iblock\CategoryProvider;

/** @var array $arParams */

$bodyHtml = '';
$valueIndex = 0;
$valueGroups = [
	'PARENT' => !empty($arParams['PARENT_VALUE']['PARAMETERS']) ? (array)$arParams['PARENT_VALUE']['PARAMETERS'] : [],
	'SELF' => !empty($arParams['VALUE']['PARAMETERS']) ? (array)$arParams['VALUE']['PARAMETERS'] : [],
];

foreach ($valueGroups as $groupName => $valueGroup)
{
	foreach ($valueGroup as $rowValue)
	{
		if (!isset($rowValue['ID'], $rowValue['VALUE'])) { continue; }

		$id = (int)$rowValue['ID'];
		$multiple = is_array($rowValue['VALUE']);
		$values = $multiple ? $rowValue['VALUE'] : [ $rowValue['VALUE'] ];
		$selectAttributes = $multiple ? 'multiple size="1"' : '';
		$optionsHtml = implode('', array_map(static function($value) {
			if (!is_string($value)) { return ''; }

			if ($value === 'Y' || $value === 'N')
			{
				return sprintf('<option value="%s" selected>%s</option>', $value, Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_BOOLEAN_' . $value) ?: $value);
			}

			if (preg_match('/^(.*)\s\[\d+]$/', $value, $matches))
			{
				return sprintf('<option value="%s" selected>%s</option>', $value, $matches[1]);
			}

			return sprintf('<option selected>%s</option>', $value);
		}, $values));
		$rowLabel = $rowValue['NAME'];
		$unitValue = null;

		if (isset($rowValue['UNIT']) && preg_match('/^(.*)\s\[\d+]$/', $rowValue['UNIT'], $unitMatches))
		{
			$rowLabel .= ", {$unitMatches[1]}";
			$unitValue = $rowValue['UNIT'];
		}

		if ($groupName === 'PARENT')
		{
			$bodyHtml .= <<<ROW
				<tr class="ym-category-parameter" data-entity="parentRow" data-id="{$id}">
				    <td class="ym-category-parameter__title">
		                <span class="ym-category-parameter__label">{$rowLabel}</span>
		                <span class="ym-category-parameter__hint" data-entity="hint"></span>
		            </td>
					<td class="ym-category-parameter__field" data-entity="field">
						<select class="ym-category-parameter__control" data-entity="value" disabled {$selectAttributes}>{$optionsHtml}</select>
					</td>
					<td class="ym-category-parameter__actions"></td>
				</td>
ROW;
		}
		else
		{
			$valueName = sprintf('%s[PARAMETERS][%s]', $arParams['~CONTROL_NAME'], $valueIndex);
			$valueNameSuffix = $multiple ? '[]' : '';
			$deleteTitle = Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_DELETE');
			$unitControl = $unitValue !== null
				? sprintf('<input type="hidden" name="%s[UNIT]" value="%s" data-entity="name" />', $valueName, $unitValue)
				: '';

			$bodyHtml .= <<<ROW
		        <tr class="ym-category-parameter" data-entity="parameterRow" data-id="{$id}">
				    <td class="ym-category-parameter__title">
				        <input type="hidden" name="{$valueName}[ID]" value="{$id}" />
				        <input type="hidden" name="{$valueName}[NAME]" value="{$rowValue['NAME']}" />
				        {$unitControl}
		                <span class="ym-category-parameter__label">{$rowLabel}</span>
		                <span class="ym-category-parameter__hint" data-entity="hint"></span>
		            </td>
					<td class="ym-category-parameter__field" data-entity="field">
						<select class="ym-category-parameter__control" name="{$valueName}[VALUE]{$valueNameSuffix}" data-entity="value" {$selectAttributes}>{$optionsHtml}</select>
					</td>
					<td class="ym-category-parameter__actions">
						<button class="ym-category-parameter__delete" type="button" data-entity="delete">{$deleteTitle}</button>
					</td>
				</tr>
ROW;

			++$valueIndex;
		}
	}
}

$emptyClass = $bodyHtml === '' ? 'is--empty' : '';
$addTitle = Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_ADD');
$addButtonClass = in_array($arParams['THEME'], [ CategoryProvider::THEME_GRID, CategoryProvider::THEME_TAB ], true) ? 'style--link' : 'adm-btn';

return <<<LAYOUT
    <table class="ym-category-parameters {$emptyClass}" data-entity="parameters">
        {$bodyHtml}
    </table>
    <button class="ym-category-parameters__add {$addButtonClass}" type="button" data-entity="parametersFactory">
		<span class="ym-category-parameters__add-text">{$addTitle}</span> 
		<span class="ym-category-parameters__add-caret">&#9660;</span>
	</button>
	<span class="ym-category-parameters__info">
		<span class="ym-category-parameters__info-hint" data-entity="addHint"></span>
		<span class="ym-category-parameters__info-state" data-entity="state"></span>
	</span>
LAYOUT;
