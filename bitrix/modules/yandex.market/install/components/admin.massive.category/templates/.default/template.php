<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Yandex\Market\Components\AdminMassiveCategory;
use Yandex\Market\Ui\Extension;
use Bitrix\Main\Web\Json;

/** @var CMain $APPLICATION */
/** @var AdminMassiveCategory $component */
/** @var array $arParams */
/** @var array $arResult */
/** @var string $templateFolder */

$loaderScripts = Extension::assets('@Ui.AssetsLoader');
$loaderScripts = Extension::injectFileUrl($loaderScripts);

?>
<!--suppress JSUnresolvedReference -->
<script>
	(window.BX || top.BX).loadScript(<?= Json::encode($loaderScripts['js']) ?>, () => {
		(window.BX || top.BX).YandexMarket.Ui.AssetsLoader.load(<?= Json::encode(Extension::injectFileUrl([
			'css' => $templateFolder . '/style.css',
		])) ?>)
	})
</script>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>" method="post">
	<input type="hidden" name="MASSIVE_ACTION" value="save" />
	<input type="hidden" name="IBLOCK_ID" value="<?= $arResult['IBLOCK_ID'] ?>" />

	<?php
	if ($arResult['SELECTED_LIMITED'])
	{
		echo BeginNote();
		echo $component->getMessage('ELEMENTS_LIMITED', ['#COUNT#' => $arResult['SELECTED_LIMIT']]);
		echo EndNote();
	}

	$index = 0;

	foreach ($arResult['GROUPS'] as $group)
	{
		?>
		<div class="ym-massive-edit-section">
			<input type="hidden" name="VALUES[<?= $index ?>][ELEMENT_ID]" value="<?= implode(',', $group['ELEMENT_ID']) ?>" />
			<input type="hidden" name="VALUES[<?= $index ?>][SECTION_ID]" value="<?= $group['SECTION_ID'] ?>" />
			<input type="hidden" name="VALUES[<?= $index ?>][PARAMETER_ID]" value="<?= implode(',', array_column($group['VALUE']['PARAMETERS'], 'ID')) ?>" />
			<?php
			if ($group['SECTION_EXPAND'] !== null)
			{
				$detailCountOverlap = (count($group['ELEMENT_ID']) > AdminMassiveCategory::SHOW_DETAILS_LIMIT);
				$detailsElements = $detailCountOverlap
					? array_slice($group['ELEMENT_ID'], 0, AdminMassiveCategory::SHOW_DETAILS_LIMIT)
					: $group['ELEMENT_ID'];

				?>
				<details class="ym-massive-edit-section__heading">
					<summary>
						<span class="ym-massive-edit-section__title"><?= $group['SECTION_TITLE'] ?></span>
						<span class="ym-massive-edit-section__summary"><?= $group['SECTION_EXPAND'] ?></span>
					</summary>
					<ul>
						<?php
						echo implode(PHP_EOL, array_map(static function ($elementId) use ($group) {
							return sprintf('<li>[%s] %s</li>', $elementId, $group['ELEMENT_NAME'][$elementId]);
						}, $detailsElements));

						if ($detailCountOverlap)
						{
							echo PHP_EOL . '<li>...</li>';
						}
						?>
					</ul>
				</details>
				<?php
			}
			else
			{
				?>
				<div class="ym-massive-edit-section__heading">
					<span class="ym-massive-edit-section__title"><?= $group['SECTION_TITLE'] ?></span>
				</div>
				<?php
			}

			echo $APPLICATION->IncludeComponent(
				'yandex.market:admin.property.category',
				'',
				[
					'PROPERTY_TYPE' => $arResult['PROPERTY_TYPE'],
					'PROPERTY_ID' => $arResult['PROPERTY_ID'],
					'MULTIPLE' => 'N',
					'DELAYED' => 'Y',
					'VALUE' => $group['VALUE'],
					'PARENT_VALUE' => empty($group['VALUE']['CATEGORY']) ? $group['PARENT_VALUE'] : null,
					'CONTROL_NAME' => sprintf('VALUES[%s][VALUE]', $index),
					'FORM_TYPE' => $group['FORM_TYPE'],
					'FORM_FIELDS' => $group['FORM_FIELDS'],
					'FORM_PAYLOAD' => $group['FORM_PAYLOAD'],
					'THEME' => $group['THEME'],
				],
				false,
				[ 'HIDE_ICONS' => 'Y' ]
			);
			?>
		</div>
		<?php

		++$index;
	}
	?>

</form>
