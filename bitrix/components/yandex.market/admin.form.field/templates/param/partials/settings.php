<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField\Helper\Attributes;
use Bitrix\Main\Localization\Loc;

/** @var $arResult array */
/** @var $arParams array */
/** @var $tagId string */
/** @var $tag \Yandex\Market\Export\Xml\Tag\Base */
/** @var $isTagPlaceholder bool */
/** @var $tagValue array */
/** @var $tagName string */
/** @var $tagInputName string */
/** @var $this CBitrixComponentTemplate */

$context = (array)$arParams['CONTEXT'];
$settings = $tag->getSettingsDescription($context);

if (is_array($settings))
{
	$settings = array_filter($settings, static function($setting, $name) use ($tagValue) {
		return empty($setting['DEPRECATED']) || !empty($tagValue['SETTINGS'][$name]);
	}, ARRAY_FILTER_USE_BOTH);
}

if (empty($settings)) { return; }

?>
<tr class="b-param-table__settings-wrap">
	<td class="b-param-table__settings-cell width--param-label"></td>
	<td class="b-param-table__settings-cell" colspan="3">
		<?php
		if ($tagName === 'url')
		{
			$hasFilled = false;

			if (!empty($tagValue['SETTINGS']) && is_array($tagValue['SETTINGS']))
			{
				foreach ($tagValue['SETTINGS'] as $settingValue)
				{
					if (!empty($settingValue['FIELD']))
					{
						$hasFilled = true;
						break;
					}
				}
			}

			?>
			<details <?= $hasFilled ? 'open' : '' ?>>
				<summary class="b-link target--none"><?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_SETTINGS_UTM_TOGGLE') ?></summary>
			<?php
		}

		?>
		<table class="b-param-table__settings <?= $tagName === 'url' ? 'layout--utm' : '' ?> js-param-tag__child" data-plugin="Field.Param.TagSettings" data-name="SETTINGS" data-tag="<?= $tagId ?>">
			<?php
			foreach ($settings as $settingName => $setting)
			{
				$settingsRowAttributes = [
					'class' => 'b-param-table__setting js-param-tag-settings__child',
					'data-plugin' => 'Field.Param.TagSetting',
				];

				if ($isTagPlaceholder)
				{
					$inputName = null;
					$inputValue = isset($setting['DEFAULT_VALUE']) ? $setting['DEFAULT_VALUE'] : null;
				}
				else
				{
					$inputName = "{$tagInputName}[SETTINGS][$settingName]";
					$inputValue = isset($tagValue['SETTINGS'][$settingName]) ? $tagValue['SETTINGS'][$settingName] : null;
				}

				if (isset($setting['GROUP']))
				{
					$settingGroup = $setting['GROUP'];

					if ($setting['TYPE'] === 'param') { $settingGroup .= ".{$context['IBLOCK_ID']}"; }

					$settingsRowAttributes['data-group'] = $settingGroup;

					if (array_key_exists($settingGroup, $arResult['SETTINGS_GROUPS']))
					{
						$settingsRowAttributes['class'] .= ' is--shadow';
						$inputValue = $arResult['SETTINGS_GROUPS'][$settingGroup];
					}
					else if (!$arParams['ENABLED'])
					{
						$settingsRowAttributes['class'] .= ' is--shadow';
						$settingsRowAttributes['data-disabled'] = true;
					}
					else if (!$isTagPlaceholder)
					{
						$arResult['SETTINGS_GROUPS'][$settingGroup] = $inputValue;
					}
				}

				// header

				?>
				<tr <?= Attributes::stringify($settingsRowAttributes) ?>>
					<td class="b-param-table__setting-label">
						<?php
						if (isset($setting['DESCRIPTION']))
						{
							?>
							<span class="b-icon icon--question size--small indent--right b-tag-tooltip--holder">
								<span class="b-tag-tooltip--content"><?= $setting['DESCRIPTION'] ?></span>
							</span><?php
						}

						echo $setting['TITLE'] . ':';
						?>
					</td>
					<td class="b-param-table__setting-value">
				<?php

				// body

				switch ($setting['TYPE'])
				{
					case 'enumeration':
						?>
						<select
							class="js-param-tag-setting__input"
							<?= $inputName !== null ? 'name="' . $inputName . '"' : '' ?>
							style="max-width: 220px;"
							data-name="<?= $settingName ?>"
							data-tag="<?= $tagId ?>"
						>
							<?php
							foreach ($setting['VALUES'] as $option)
							{
								?>
								<option value="<?= $option['ID'] ?>" <?= (string)$option['ID'] === (string)$inputValue ? 'selected' : '' ?>><?= Market\Utils::htmlEscape($option['VALUE']) ?></option>
								<?php
							}
							?>
						</select>
						<?php
					break;

					case 'param':
						$attributeFullType = null;
						$availableTypes = null;
						$defaultSource = isset($setting['DEFAULT']['TYPE']) ? $setting['DEFAULT']['TYPE'] : Market\Export\Entity\Manager::TYPE_TEXT;
						$defaultField = isset($setting['DEFAULT']['FIELD']) ? $setting['DEFAULT']['FIELD'] : '';
						$sourceType = !empty($inputValue['TYPE']) ? $inputValue['TYPE'] : $defaultSource;
						$sourceField = !empty($inputValue['FIELD']) ? $inputValue['FIELD'] : $defaultField;
						$selectedTypeId = null;
						$fieldInputName = $inputName  !== null ? $inputName . '[FIELD]' : null;
						$fieldPartName = 'FIELD';
						$isDefined = false;
						$disabledTypes = [
							$arResult['RECOMMENDATION_TYPE'] => true,
						];

						?>
						<div class="b-param-setting-source js-param-tag-setting__child" data-plugin="Field.Param.Node" data-name="<?= $settingName ?>">
							<div class="b-param-setting-source__cell">
								<select class="b-param-table__input js-param-node__source js-param-node__input" data-name="TYPE" <?php

									if ($inputName !== null)
									{
										echo 'name="' . $inputName . '[TYPE]' . '"';
									}

								?>>
									<?php
									foreach ($arResult['SOURCE_TYPE_ENUM'] as $typeEnum)
									{
										if (isset($disabledTypes[$typeEnum['ID']])) { continue; }

										$isSelected = ($typeEnum['ID'] === $sourceType);
										$isDefault = ($typeEnum['ID'] === $defaultSource);

										if ($isSelected || $selectedTypeId === null)
										{
											$selectedTypeId = $typeEnum['ID'];
										}

										?>
										<option value="<?= $typeEnum['ID'] ?>" <?= $isSelected ? 'selected': '' ?> <?= $isDefault ? 'data-default="true"' : '' ?>><?= Market\Utils::htmlEscape($typeEnum['VALUE']) ?></option>
										<?php
									}
									?>
								</select>
							</div>
							<div class="b-param-setting-source__cell">
								<?php
								include __DIR__ . '/field-control.php';
								?>
							</div>
						</div>
						<?php
					break;

					case 'boolean':
						?>
						<label>
							<input
								class="adm-designed-checkbox js-param-tag-setting__input"
								type="checkbox"
								value="1"
								<?= $inputName !== null ? 'name="' . $inputName . '"' : '' ?>
								<?= (string)$inputValue === '1' ? 'checked' : '' ?>
								data-name="<?= $settingName ?>"
							/>
							<span class="adm-designed-checkbox-label"></span>
						</label>
						<?php
					break;

					default:
						?>
						<input
							class="js-param-tag-setting__input"
							type="text"
							<?= $inputName !== null ? 'name="' . $inputName . '"' : '' ?>
							value="<?= htmlspecialcharsbx($inputValue) ?>"
							data-name="<?= $settingName ?>"
						/>
						<?php
					break;
				}

				// footer

				?>
					</td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
		if ($tagName === 'url') { echo '</details>'; }
		?>
	</td>
</tr>