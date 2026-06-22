<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;

/** @var $component Yandex\Market\Components\AdminFormEdit */
/** @var $specialFields array */
/** @var $isActiveTab bool */
/** @global $APPLICATION */

global $USER_FIELD_MANAGER;

Loc::loadMessages(__FILE__);

$hasCommonFields = !empty($commonFields);
$specialFieldGroups = [];

foreach ($specialFields as $name)
{
	$field = $component->getField($name);

	if (preg_match('/^(.*)\[([^]]+)]$/', $field['FIELD_NAME'], $matches))
	{
		list(, $field['BASE_NAME'], $groupName) = $matches;
	}
	else
	{
		$field['BASE_NAME'] = $field['FIELD_NAME'];
		$groupName = $field['FIELD_NAME'];
	}

	if (!isset($specialFieldGroups[$field['BASE_NAME']]))
	{
		$specialFieldGroups[$field['BASE_NAME']] = [];
	}

	$specialFieldGroups[$field['BASE_NAME']][$groupName] = $field;
}

if (empty($specialFieldGroups)) { return; }

$groupIndex = 0;

?>
<tr>
	<td class="<?= $hasCommonFields ? 'b-form-section-holder' : '' ?>" colspan="2">
		<div class="<?= $hasCommonFields ? 'b-form-section' : '' ?>">
			<?php
			foreach ($specialFieldGroups as $specialFieldGroup)
			{
				if (!isset($specialFieldGroup['FILTER'])) { continue; }

				$groupValue = $component->getFieldValue([
					'FIELD_NAME' => $specialFieldGroup['FILTER']['BASE_NAME'],
				]);

				if (!isset($groupValue['IBLOCK_ID'])) { continue; }

				$groupBaseName = $specialFieldGroup['FILTER']['BASE_NAME'];

				if ($groupIndex === 0)
				{
					?>
					<span class="b-heading level--2"><?= $component->getFieldTitle($specialFieldGroup['FILTER']) ?></span>
					<?php
				}
				?>
				<h3 class="b-heading level--3 <?= $groupIndex === 0 ? 'pos--top' : 'spacing--2x1' ?>">
					<?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_PRODUCT_FILTER_IBLOCK_SECTION', [
						'#IBLOCK_NAME#' => !empty($groupValue['CONTEXT']['IBLOCK_NAME']) ? '&laquo;' . $groupValue['CONTEXT']['IBLOCK_NAME'] . '&raquo;' : '#' . $groupValue['IBLOCK_ID']
					]) ?>
				</h3>
				<input type="hidden" name="<?= $groupBaseName . '[ID]' ?>" value="<?= isset($groupValue['ID']) ? $groupValue['ID'] : '' ?>" />
				<input type="hidden" name="<?= $groupBaseName . '[IBLOCK_ID]' ?>" value="<?= $groupValue['IBLOCK_ID'] ?>" />
				<div class="b-form-panel">
					<div class="b-form-panel__section fill--primary b-compensate compensate--1x1">
						<?php
						$APPLICATION->IncludeComponent('yandex.market:admin.form.field', 'filter', [
							'INPUT_NAME' => $specialFieldGroup['FILTER']['FIELD_NAME'],
							'MULTIPLE' => 'Y',
							'VALUE' => isset($groupValue['FILTER']) ? $groupValue['FILTER'] : null,
							'CONTEXT' => $groupValue['CONTEXT'],
							'FILTER_BASE_NAME' => preg_replace('/\[\d+]$/', '', $specialFieldGroup['FILTER']['BASE_NAME']),
							'REFRESH_COUNT_ON_LOAD' => $isActiveTab,
							'LANG_ADD_BUTTON' => Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_PRODUCT_FILTER_ADD_BUTTON'),
							'EXPORT_ADD_BUTTON' => $groupBaseName . 'FILTER_ADD',
							'EXPORT_LEFT_COUNT' => $groupBaseName . 'LEFT_COUNT',
							'EXPORT_LEFT_MESSAGE' => $groupBaseName . 'LEFT_MESSAGE',
							'NEED_LEFT_COUNT' => isset($specialFieldGroup['EXPORT_ALL']) ? 'Y' : 'N',
							'ALLOW_NAME' => 'Y',
						]);

						if (isset($specialFieldGroup['EXPORT_ALL']))
						{
							?>
							<div class="b-grid spacing--1x1 b-compensate compensate--3x4">
								<div class="b-grid__item spacing--3x4">
									<?= $component->getFieldHtml($specialFieldGroup['EXPORT_ALL']) ?>
									<label for="<?= $specialFieldGroup['EXPORT_ALL']['FIELD_NAME'] ?>">
										<?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_INTERFACE_FORM_EXPORT_ALL_LABEL') ?>
										<?= $APPLICATION->GetViewContent($groupBaseName . 'LEFT_COUNT') ?>
									</label>
								</div>
								<div class="b-grid__item spacing--3x4">
									<?= $APPLICATION->GetViewContent($groupBaseName . 'LEFT_MESSAGE') ?>
								</div>
							</div>
							<?php
						}

						echo $APPLICATION->GetViewContent($groupBaseName . 'FILTER_ADD');
						?>
					</div>
				</div>
				<?php

				$groupIndex++;
			}
			?>
		</div>
	</td>
</tr>
