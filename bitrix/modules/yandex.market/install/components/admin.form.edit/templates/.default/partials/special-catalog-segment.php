<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\UserField;

/** @var Yandex\Market\Components\AdminFormEdit $component */
/** @var string[] $specialFields */
/** @var array $arParams */
/** @var array $arResult */
/** @var bool $isActiveTab */

$hasCommonFields = !empty($commonFields);

?>
<tr>
	<td class="<?= $hasCommonFields ? 'b-form-section-holder' : '' ?>" colspan="2">
		<div class="<?= $hasCommonFields ? 'b-form-section' : '' ?>">
			<?php
			$groupIndex = 0;
			$previousIblockId = null;

			foreach ($specialFields as $name)
			{
				$field = $component->getField($name);

				if (!preg_match('/^(.*)\[([^]]+)]$/', $field['FIELD_NAME'], $matches)) { continue; }

				$groupValue = $component->getFieldValue([ 'FIELD_NAME' => $matches[1] ]);

				if (empty($groupValue['IBLOCK_ID'])) { continue; }

				if ($groupIndex === 0)
				{
					?>
					<span class="b-heading level--2"><?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_PRODUCT_PARAM') ?></span>
					<?php
				}

				if ($previousIblockId !== $groupValue['IBLOCK_ID'])
				{
					?>
					<h3 class="b-heading level--3 <?= $groupIndex === 0 ? 'pos--top' : 'spacing--2x1' ?>">
						<?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_PRODUCT_PARAM_IBLOCK_SECTION', [
							'#IBLOCK_NAME#' => !empty($groupValue['CONTEXT']['IBLOCK_NAME']) ? '&laquo;' . $groupValue['CONTEXT']['IBLOCK_NAME'] . '&raquo;' : '#' . $groupValue['IBLOCK_ID']
						]) ?>
					</h3>
					<?php

					$previousIblockId = $groupValue['IBLOCK_ID'];
				}

				$className = $field['USER_TYPE']['CLASS_NAME'];

				if (is_subclass_of($className, UserField\Form\FullLineLayout::class))
				{
					$fieldValue = $component->getFieldValue($field);
					$field = UserField\Helper\Field::extendValue($field, $fieldValue, $arResult['ITEM']);

					echo $className::getEditFullLineHtml($field, [
						'NAME' => $field['FIELD_NAME'],
						'VALUE' => $fieldValue,
						'ACTIVE_TAB' => $isActiveTab,
					]);
				}
				else
				{
					ShowError('only segment supported');
				}

				++$groupIndex;
			}
			?>
		</div>
	</td>
</tr>
