<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField;

/** @var array $arResult */
/** @var array $field */
/** @var boolean $isActiveTab */
/** @var Yandex\Market\Components\AdminFormEdit $component */

$className = $field['USER_TYPE']['CLASS_NAME'];

if (is_subclass_of($className, UserField\Form\FullLineLayout::class))
{
	$fieldValue = $component->getFieldValue($field);
	$field = UserField\Helper\Field::extendValue($field, $fieldValue, $arResult['ITEM']);

	?>
	<tr>
		<td colspan="2"><?= $className::getEditFullLineHtml($field, [
			'NAME' => $field['FIELD_NAME'],
			'VALUE' => $fieldValue,
			'ACTIVE_TAB' => $isActiveTab,
		]) ?></td>
	</tr>
	<?php

	return;
}

$rowAttributes = [];
$additionPreviousAttributes = [];
$additionNextAttributes = [];
$fieldControl = $component->getFieldHtml($field, null, true);
$fieldValign = $fieldControl !== null && $fieldControl['VALIGN'] ? $fieldControl['VALIGN'] : 'middle';
$fieldPushTitle = null;
$hasDescription = isset($field['DESCRIPTION']);
$hasNote = isset($field['NOTE']);
$hasAdditionalRow = ($hasDescription || $hasNote);

if (!empty($field['SETTINGS']['VALIGN']))
{
	$fieldValign = $field['SETTINGS']['VALIGN'];
}

if ($fieldValign === 'top')
{
	if (!empty($field['SETTINGS']['VALIGN_PUSH']))
	{
		$fieldPushTitle = $field['SETTINGS']['VALIGN_PUSH'] === true ? 'top' : $field['SETTINGS']['VALIGN_PUSH'];
	}
	else if ($field['CONTROL'] !== null)
	{
		$controlCount = (
			mb_substr_count($field['CONTROL'], ' type="text"')
			+ mb_substr_count($field['CONTROL'], ' type="number"')
			+ mb_substr_count($field['CONTROL'], '<select')
			+ mb_substr_count($field['CONTROL'], '<textarea')
		);

		$fieldPushTitle = ($controlCount === 1) ? 'top' : null;
	}
}

if (isset($field['DEPEND']))
{
	Market\Ui\Assets::loadPlugin('Ui.Input.DependField');

	$rowAttributes['class'] = 'js-plugin';
	$rowAttributes['data-plugin'] = 'Ui.Input.DependField';
	$rowAttributes['data-depend'] = Market\Utils::jsonEncode($field['DEPEND'], JSON_UNESCAPED_UNICODE);

	$additionPreviousAttributes['class'] = 'js-depend-field-prev-addition';
	$additionNextAttributes['class'] = 'js-depend-field-next-addition';

	if ($field['DEPEND_HIDDEN'])
	{
		$rowAttributes['class'] .= ' is--hidden';
		$additionPreviousAttributes['class'] .= ' is--hidden';
		$additionNextAttributes['class'] .= ' is--hidden';
	}
}

$ajaxReloaderFieldKey = isset($field['FIELD_GROUP']) ? $field['FIELD_GROUP'] : $field['FIELD_NAME'];

if (isset($arParams['AJAX_RELOADER'][$ajaxReloaderFieldKey]))
{
	$rowAttributes['class'] = (isset($rowAttributes['class']) ? $rowAttributes['class'] . ' ' : '') . 'js-ajax-reloader__field';
	$rowAttributes['data-reloader-name'] = $ajaxReloaderFieldKey;
	$rowAttributes['data-reloader-target'] = implode(', ', $arParams['AJAX_RELOADER'][$ajaxReloaderFieldKey]['TARGET']);

	if (!empty($arParams['AJAX_RELOADER'][$ajaxReloaderFieldKey]['SIGNIFICANT']))
	{
		$rowAttributes['data-reloader-significant'] = implode(', ', $arParams['AJAX_RELOADER'][$ajaxReloaderFieldKey]['SIGNIFICANT']);
	}
}

if (isset($field['INTRO']))
{
	?>
	<tr <?= UserField\Helper\Attributes::stringify($additionPreviousAttributes) ?>>
		<td class="adm-detail-content-cell-l" width="40%" align="right" valign="top">&nbsp;</td>
		<td class="adm-detail-content-cell-r" width="60%">
			<small><?= $field['INTRO'] ?></small>
		</td>
	</tr>
	<?php
}
?>
<tr <?= UserField\Helper\Attributes::stringify($rowAttributes) ?>>
	<td class="adm-detail-content-cell-l <?= $hasAdditionalRow ? 'pos-inner--bottom' : '' ?> <?= $fieldPushTitle ? 'push--' . $fieldPushTitle : '' ?>" width="40%" align="right" valign="<?= $fieldValign ?>">
		<?php
		include __DIR__ . '/field-title.php';
		?>
	</td>
	<td class="adm-detail-content-cell-r <?= $hasAdditionalRow ? 'pos-inner--bottom' : '' ?>" width="60%">
		<?php
		if ($fieldControl !== null)
		{
			echo $fieldControl['CONTROL'];
		}

		if (!empty($field['SETTINGS']['BUTTONS']))
		{
			$buttons = $field['SETTINGS']['BUTTONS'];

			include __DIR__ . '/field-buttons.php';
		}
		?>
	</td>
</tr>
<?php

if ($hasAdditionalRow)
{
	?>
	<tr <?= UserField\Helper\Attributes::stringify($additionNextAttributes) ?>>
		<td class="adm-detail-content-cell-l pos-inner--top" width="40%" align="right" valign="top">&nbsp;</td>
		<td class="adm-detail-content-cell-r pos-inner--top" width="60%">
			<?php
			if ($hasDescription)
			{
				echo '<small>' . $field['DESCRIPTION'] . '</small>';
			}

			if ($hasNote)
			{
				echo BeginNote();
				echo $field['NOTE'];
				echo EndNote();
			}
			?>
		</td>
	</tr>
	<?php
}