<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField\Helper\Attributes;
use Bitrix\Main\Localization\Loc;

/** @var array $arResult */
/** @var array $arParams */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateFolder */
/** @var CMain $APPLICATION */

if (isset($arResult['ERROR']))
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => $arResult['ERROR'],
		'HTML' => true
	]);

	return;
}

Market\Ui\Extension::load([
    '@lib.select2',
    '@Field.Reference',
    '@Ui.Input.TagInput',
    '@Ui.Input.Template',
    '@Ui.Input.Formula',
    '@Source.Manager',
]);

$this->addExternalJs($templateFolder . '/build.js');

$lang = [
	'SELECT_PLACEHOLDER' => Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_SELECT_PLACEHOLDER'),
];

$fieldId = 'param-' . $this->randString(5);
$addTagList = [];

?>
<div class="b-param-table js-plugin js-param-manager" <?= Attributes::stringify([
	'id' => $fieldId,
	'data-plugin' => 'Field.Param.TagGroup',
]) ?>>
    <table class="b-param-table__row">
        <tr>
            <th class="b-param-table__cell for--label">&nbsp;</th>
            <th class="b-param-table__cell width--param-source-cell"><?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_HEADER_SOURCE') ?></th>
            <th class="b-param-table__cell width--param-field-cell"><?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_HEADER_FIELD') ?></th>
            <th>&nbsp;</th>
        </tr>
    </table>
    <?php

    foreach ($arParams['GROUPS'] as $group)
    {
        $tagIndex = 0;

        ?>
        <div class="js-param-tag-group__item" <?= Attributes::stringify([
            'data-plugin' => 'Field.Param.TagCollection',
            'data-base-name' => $group['INPUT_NAME'],
            'data-item-element' => '.js-param-tag-collection__item.level--0',
            'data-item-delete-element' => '.js-param-tag-collection__item-delete.level--0',
        ]) ?>>
            <?php
            if (!empty($group['TITLE']) && $arParams['GROUP_FLAT'] !== 'Y')
            {
                ?>
                <table class="b-param-table__row js-param-tag-collection__title <?= $group['ACTIVE'] ? '' : 'is--hidden' ?>">
                    <tr>
                        <td class="b-param-table__cell for--label">&nbsp;</td>
                        <td class="b-param-table__cell"><strong class="b-param-table__group"><?= $group['TITLE'] ?></strong></td>
                    </tr>
                </table>
                <?php
            }

            /** @var \Yandex\Market\Export\Xml\Tag\Base $tag */
            foreach ($group['TAGS'] as $tagId => $tag)
            {
                if (mb_strpos($tagId, '.') !== false) { continue; } // children

                $tagLevel = 0;
                $tagValues = isset($group['TAG_VALUE'][$tagId]) ? $group['TAG_VALUE'][$tagId] : [];
                $parentBaseId = '';
                $parentInputName = $group['INPUT_NAME'];
                $isParentPlaceholder = $arParams['PLACEHOLDER'];

                if (empty($tagValues) && $tag->isDeprecated()) { continue; }

                include __DIR__ . '/partials/tag.php';
            }
            ?>
        </div>
        <?php
    }
	?>
	<div class="b-param-table__footer">
		<table class="b-param-table__row">
			<tr>
				<td class="b-param-table__cell width--param-label">&nbsp;</td>
				<td class="b-param-table__cell">
                    <button <?= Attributes::stringify([
                        'class' => 'adm-btn js-param-tag-group__factory ' . ($arResult['TAG_FACTORY_ACTIVE'] ? '' : 'is--hidden'),
                        'type' => 'button',
                        'tabindex' => 0,
                        'data-groups' => array_map(
                            static function(array $group) { return array_intersect_key($group, [ 'TITLE' => true, 'TAG_FACTORY' => true ]); },
                            $arParams['GROUPS']
                        ),
						'data-group-flat' => ($arParams['GROUP_FLAT'] === 'Y' ? 'true' : null),
                    ]) ?>><?= Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_ADD_TAG') ?></button>
					<?php
					if (!empty($arResult['DOCUMENTATION_LINK']))
					{
						?>
						<div class="b-admin-message-list spacing--1x2">
							<?php
							\CAdminMessage::ShowMessage([
								'TYPE' => 'OK',
								'MESSAGE' => Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_DOCUMENTATION_TITLE'),
								'DETAILS' => implode(', ', array_map(
                                    static function($url) { return "<a href=\"{$url}\">{$url}</a>"; },
                                    $arResult['DOCUMENTATION_LINK']
                                )),
								'HTML' => true
							]);
							?>
						</div>
						<?php
					}

					if (!empty($arResult['DOCUMENTATION_BETA']))
					{
						?>
						<div class="b-admin-message-list">
							<?php
							\CAdminMessage::ShowMessage([
								'TYPE' => 'ERROR',
								'MESSAGE' => Loc::getMessage('YANDEX_MARKET_T_ADMIN_FIELD_PARAM_DOCUMENTATION_BETA', [
									'#FORMAT_NAME#' => $arParams['CONTEXT']['EXPORT_FORMAT']
								]),
								'HTML' => true
							]);
							?>
						</div>
						<?php
					}
					?>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php
if (!empty($arResult['SETTINGS_GROUPS']))
{
	$APPLICATION->SetPageProperty('YAMARKET_PARAM_SETTINGS_GROUPS', $arResult['SETTINGS_GROUPS']);
}

$managerData = [
	'types' => array_values($arResult['SOURCE_TYPE_ENUM']),
	'fields' => array_values($arResult['SOURCE_FIELD_ENUM']),
	'recommendation' => $arResult['RECOMMENDATION'],
	'typeMap' => $arResult['TYPE_MAP_JS']
];
?>
<script>
	(function() {
		const Source = BX.namespace('YandexMarket.Source');
		const utils = BX.namespace('YandexMarket.Utils');

		// init source manager

		new Source.Manager('#<?= $fieldId ?>', <?= Market\Utils::jsonEncode($managerData, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR) ?>);

		// extend lang

		utils.registerLang(<?= Market\Utils::jsonEncode($lang, JSON_UNESCAPED_UNICODE) ?>, 'YANDEX_MARKET_FIELD_PARAM_');
	})();
</script>