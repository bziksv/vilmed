<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;

/** @var Yandex\Market\Components\AdminFormEdit $component */
/** @var array $fields */
/** @var array $arParams */
/** @var CMain $APPLICATION */
/** @var bool $isActiveTab */

$productFields = [];

foreach ($fields as $name)
{
	$field = $component->getField($name);
	$code = $field['FIELD_GROUP'] ?: $field['FIELD_NAME'];

	if (!empty($field['DEPEND_HIDDEN'])) { continue; }
	if (!in_array($code, $arParams['PRODUCT_PARAM_FIELDS'], true)) { continue; }

	if (preg_match('/^(.*)\[([^]]+)]$/', $field['FIELD_NAME'], $matches))
	{
		list(, $baseName, $selfName) = $matches;
	}
	else
	{
		$baseName = $field['FIELD_NAME'];
		$selfName = $field['FIELD_NAME'];
	}

	$iblockValue = $component->getFieldValue([ 'FIELD_NAME' => $baseName ]);

	if (!isset($iblockValue['IBLOCK_ID'])) { continue; }

	list($selfGroup, $selfType) = explode('_', $selfName, 2);

	if ($selfType === null) { $selfType = $selfGroup; }

	if (!isset($productFields[$iblockValue['IBLOCK_ID']]))
	{
		$productFields[$iblockValue['IBLOCK_ID']] = [
			'VALUE' => $iblockValue,
			'GROUPS' => [],
		];
	}

	if (!isset($productFields[$iblockValue['IBLOCK_ID']]['GROUPS'][$selfGroup]))
	{
		$productFields[$iblockValue['IBLOCK_ID']]['GROUPS'][$selfGroup] = [];
	}

	$productFields[$iblockValue['IBLOCK_ID']]['GROUPS'][$selfGroup][$selfType] = $field;
}

foreach ($productFields as $iblockId => $productIblock)
{
	$iblockValue = $productIblock['VALUE'];

	?>
	<tr class="heading">
		<td colspan="2">
			<?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_INTERFACE_FORM_IBLOCK_SECTION', [
				'#IBLOCK_NAME#' => !empty($iblockValue['CONTEXT']['IBLOCK_NAME']) ? '&laquo;' . $iblockValue['CONTEXT']['IBLOCK_NAME'] . '&raquo;' : '#' . $iblockValue['IBLOCK_ID']
			]) ?>
		</td>
	</tr>
	<?php
	include __DIR__ . '/warning.php';

	foreach ($productIblock['GROUPS'] as $group => $fields)
	{
		if (!isset($fields['PARAM'])) { continue; }

		?>
		<tr>
			<td colspan="2">
				<?php
				$APPLICATION->IncludeComponent('yandex.market:admin.form.field', 'param', [
					'INPUT_NAME' => $fields['PARAM']['FIELD_NAME'],
					'MULTIPLE' => 'Y',
					'VALUE' => $component->getFieldValue($fields['PARAM']),
					'CONTEXT' => $iblockValue['CONTEXT'],
					'FORMAT' => $fields['PARAM']['FORMAT'],
					'ACTIVE_TAB' => $isActiveTab,
				]);
				?>
			</td>
		</tr>
		<?php
	}
}