<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Titlo\Relevance\Config;

/** @global CMain $APPLICATION */
/** @global CUser $USER */

$moduleId = 'titlo.relevance';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
	return;
}

Loader::includeModule($moduleId);

$presets = Config::apiBasePresets();
$defaultApiBase = Config::API_BASE_PROD;

$note = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
	$preset = trim((string) ($_POST['api_base_preset'] ?? ''));
	$custom = rtrim(trim((string) ($_POST['api_base_custom'] ?? '')), '/');
	$customConfirm = !empty($_POST['api_base_custom_confirm']);

	if ($preset === 'custom') {
		$apiBase = $custom !== '' ? $custom : $defaultApiBase;
		if (!$customConfirm) {
			$error = Loc::getMessage('TITLO_RELEVANCE_OPT_API_BASE_CUSTOM_CONFIRM_NEEDED')
				?: 'Для произвольного API URL отметьте подтверждение (ключ уйдёт на этот хост).';
			$apiBase = null;
		} elseif (!Config::isAllowedApiBaseUrl($apiBase, true)) {
			$error = 'API base не разрешён: только https://cabinet.titlo.ru/api/v1 или http(s) на localhost / 127.0.0.1 / host.docker.internal.';
			$apiBase = null;
		}
	} elseif (isset($presets[$preset])) {
		$apiBase = $preset;
	} else {
		$apiBase = $defaultApiBase;
	}

	if ($apiBase !== null) {
		$siteUrl = rtrim(trim((string) ($_POST['site_url'] ?? '')), '/');
		if (!Config::isAllowedSiteUrl($siteUrl)) {
			$error = 'Некорректный URL сайта: нужен http(s)://hostname (без private IP, кроме localhost).';
			$apiBase = null;
		}
	}

	if ($apiBase !== null) {
		Option::set($moduleId, 'api_base_url', $apiBase);

		$newKey = trim((string) ($_POST['api_key'] ?? ''));
		if ($newKey !== '') {
			Option::set($moduleId, 'api_key', $newKey);
		} elseif (!empty($_POST['api_key_clear'])) {
			Option::set($moduleId, 'api_key', '');
		}
		// пустое поле без clear — ключ не трогаем

		Option::set($moduleId, 'iblock_id', (string) max(0, (int) ($_POST['iblock_id'] ?? 0)));
		Option::set($moduleId, 'site_url', $siteUrl);
		Option::set($moduleId, 'product_url_tpl', trim((string) ($_POST['product_url_tpl'] ?? '')));
		Option::set($moduleId, 'section_url_tpl', trim((string) ($_POST['section_url_tpl'] ?? '')));
		Option::set($moduleId, 'noindex_property', trim((string) ($_POST['noindex_property'] ?? '')));
		Option::set($moduleId, 'noindex_use_seo', !empty($_POST['noindex_use_seo']) ? 'Y' : 'N');
		$pickTextField = static function (string $selectKey, string $customKey, string $fallback): string {
			$custom = trim((string) ($_POST[$customKey] ?? ''));
			if ($custom !== '') {
				return Config::normalizeCatalogTextField($custom, $fallback);
			}

			return Config::normalizeCatalogTextField((string) ($_POST[$selectKey] ?? ''), $fallback);
		};
		Option::set($moduleId, 'text_field_preview', $pickTextField('text_field_preview', 'text_field_preview_custom', 'PREVIEW_TEXT'));
		Option::set($moduleId, 'text_field_detail', $pickTextField('text_field_detail', 'text_field_detail_custom', 'DETAIL_TEXT'));
		Option::set($moduleId, 'text_field_section', $pickTextField('text_field_section', 'text_field_section_custom', 'DESCRIPTION'));
		$note = Loc::getMessage('TITLO_RELEVANCE_OPTIONS_SAVED');
	}
}

$aTabs = [[
	'DIV' => 'edit1',
	'TAB' => Loc::getMessage('TITLO_RELEVANCE_OPTIONS_TAB'),
	'TITLE' => Loc::getMessage('TITLO_RELEVANCE_OPTIONS_TAB_TITLE'),
]];

$tabControl = new CAdminTabControl('titloRelevanceTabControl', $aTabs);

if ($error !== '') {
	CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => $error]);
}
if ($note !== '') {
	CAdminMessage::ShowNote($note);
}

$get = static function (string $name, string $default = '') use ($moduleId): string {
	return (string) Option::get($moduleId, $name, $default);
};

$currentApiBase = rtrim($get('api_base_url', $defaultApiBase), '/');
if ($currentApiBase === '') {
	$currentApiBase = $defaultApiBase;
}

$selectedPreset = isset($presets[$currentApiBase]) ? $currentApiBase : 'custom';
$customValue = $selectedPreset === 'custom' ? $currentApiBase : '';
$hasApiKey = Config::apiKey() !== '';

$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>
	<?php $tabControl->BeginNextTab(); ?>
	<tr>
		<td width="40%"><?= Loc::getMessage('TITLO_RELEVANCE_OPT_API_BASE') ?></td>
		<td width="60%">
			<select name="api_base_preset" id="titlo-api-base-preset" style="max-width: 520px;">
				<?php foreach ($presets as $url => $label): ?>
					<option value="<?= htmlspecialcharsbx($url) ?>"<?= $selectedPreset === $url ? ' selected' : '' ?>>
						<?= htmlspecialcharsbx($label) ?> — <?= htmlspecialcharsbx($url) ?>
					</option>
				<?php endforeach; ?>
				<option value="custom"<?= $selectedPreset === 'custom' ? ' selected' : '' ?>>
					<?= Loc::getMessage('TITLO_RELEVANCE_OPT_API_BASE_CUSTOM') ?>
				</option>
			</select>
			<div id="titlo-api-base-custom-wrap" style="margin-top:8px;<?= $selectedPreset === 'custom' ? '' : 'display:none;' ?>">
				<input type="text" name="api_base_custom" id="titlo-api-base-custom"
					value="<?= htmlspecialcharsbx($customValue) ?>" size="60"
					placeholder="https://cabinet.titlo.ru/api/v1">
				<label style="display:block;margin-top:6px;">
					<input type="checkbox" name="api_base_custom_confirm" value="Y">
					Подтверждаю произвольный host (ключ уйдёт туда)
				</label>
			</div>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_API_KEY') ?></td>
		<td>
			<?php if ($hasApiKey): ?>
				<div style="margin-bottom:6px;color:#64748b;">Ключ сохранён. Оставьте поле пустым, чтобы не менять.</div>
			<?php endif; ?>
			<input type="password" name="api_key" value="" size="60" autocomplete="new-password"
				placeholder="<?= $hasApiKey ? '•••••••• (сменить)' : '' ?>">
			<?php if ($hasApiKey): ?>
				<label style="display:block;margin-top:6px;">
					<input type="checkbox" name="api_key_clear" value="Y">
					Удалить ключ
				</label>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_IBLOCK') ?></td>
		<td>
			<input type="text" name="iblock_id" value="<?= htmlspecialcharsbx($get('iblock_id', '0')) ?>" size="10">
			<?php if ((int) $get('iblock_id', '0') <= 0): ?>
				<div class="adm-info-message" style="display:block;margin-top:6px;color:#b45309">
					Без ID инфоблока списки UF/свойств для анонса и описания пустые — только стандартные PREVIEW_TEXT / DETAIL_TEXT.
				</div>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_SITE_URL') ?></td>
		<td>
			<input type="text" name="site_url" value="<?= htmlspecialcharsbx($get('site_url', '')) ?>" size="60"
				placeholder="https://example.com">
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_PRODUCT_TPL') ?></td>
		<td>
			<input type="text" name="product_url_tpl" value="<?= htmlspecialcharsbx($get('product_url_tpl', '')) ?>" size="60"
				placeholder="/catalog/{code}/">
			<div class="adm-info-message" style="display:block;margin-top:6px;">
				<?= Loc::getMessage('TITLO_RELEVANCE_OPT_PRODUCT_TPL_HINT') ?>
			</div>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_SECTION_TPL') ?></td>
		<td>
			<input type="text" name="section_url_tpl" value="<?= htmlspecialcharsbx($get('section_url_tpl', '')) ?>" size="60"
				placeholder="/catalog/{code}/">
			<div class="adm-info-message" style="display:block;margin-top:6px;">
				<?= Loc::getMessage('TITLO_RELEVANCE_OPT_SECTION_TPL_HINT') ?>
			</div>
		</td>
	</tr>
	<?php
	$previewOptions = \Titlo\Relevance\CatalogRepository::catalogTextFieldOptions('E');
	$sectionOptions = \Titlo\Relevance\CatalogRepository::catalogTextFieldOptions('S');
	$iblockConfigured = Config::iblockId() > 0;
	$renderFieldSelect = static function (string $name, string $customName, string $current, array $options) use ($iblockConfigured): void {
		$known = [];
		$inList = false;
		echo '<select name="' . htmlspecialcharsbx($name) . '" style="max-width:520px;display:block">';
		foreach ($options as $opt) {
			$value = (string) $opt['value'];
			$known[$value] = true;
			$sel = ($current === $value);
			if ($sel) {
				$inList = true;
			}
			echo '<option value="' . htmlspecialcharsbx($value) . '"'
				. ($sel ? ' selected' : '') . '>'
				. htmlspecialcharsbx((string) $opt['label']) . '</option>';
		}
		if ($current !== '' && !isset($known[$current])) {
			$inList = true;
			echo '<option value="' . htmlspecialcharsbx($current) . '" selected>'
				. htmlspecialcharsbx($current) . '</option>';
		}
		echo '</select>';
		$customVal = $inList ? '' : $current;
		echo '<div style="margin-top:6px">'
			. htmlspecialcharsbx((string) Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_CUSTOM'))
			. ': <input type="text" name="' . htmlspecialcharsbx($customName) . '" value="'
			. htmlspecialcharsbx($customVal) . '" size="40" placeholder="UF_DESC / PROP:DESCRIPTION" style="max-width:360px">'
			. '</div>';
		if (!$iblockConfigured) {
			echo '<div class="adm-info-message" style="display:block;margin-top:6px">'
				. Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_FIELDS_NEED_IBLOCK')
				. '</div>';
		}
	};
	?>
	<tr class="heading">
		<td colspan="2">Поля текстов каталога</td>
	</tr>
	<tr>
		<td colspan="2">
			<div class="adm-info-message" style="display:block;margin:0;">
				<?= Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_FIELDS_HINT') ?>
			</div>
		</td>
	</tr>
	<tr>
		<td style="vertical-align:top;padding-top:10px"><?= Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_PREVIEW') ?></td>
		<td style="vertical-align:top">
			<?php $renderFieldSelect('text_field_preview', 'text_field_preview_custom', Config::previewTextField(), $previewOptions); ?>
		</td>
	</tr>
	<tr>
		<td style="vertical-align:top;padding-top:10px"><?= Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_DETAIL') ?></td>
		<td style="vertical-align:top">
			<?php $renderFieldSelect('text_field_detail', 'text_field_detail_custom', Config::detailTextField(), $previewOptions); ?>
		</td>
	</tr>
	<tr>
		<td style="vertical-align:top;padding-top:10px"><?= Loc::getMessage('TITLO_RELEVANCE_OPT_TEXT_SECTION') ?></td>
		<td style="vertical-align:top">
			<?php $renderFieldSelect('text_field_section', 'text_field_section_custom', Config::sectionTextField(), $sectionOptions); ?>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_NOINDEX_PROP') ?></td>
		<td>
			<input type="text" name="noindex_property" value="<?= htmlspecialcharsbx($get('noindex_property', '')) ?>" size="40"
				placeholder="">
			<div class="adm-info-message" style="display:block;margin-top:6px;">
				<?= Loc::getMessage('TITLO_RELEVANCE_OPT_NOINDEX_PROP_HINT') ?>
			</div>
		</td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('TITLO_RELEVANCE_OPT_NOINDEX_SEO') ?></td>
		<td>
			<label>
				<input type="checkbox" name="noindex_use_seo" value="Y"<?= $get('noindex_use_seo', 'Y') !== 'N' ? ' checked' : '' ?>>
				<?= Loc::getMessage('TITLO_RELEVANCE_OPT_NOINDEX_SEO_LABEL') ?>
			</label>
		</td>
	</tr>
	<?php
	$tabControl->Buttons([
		'btnSave' => true,
		'btnApply' => true,
		'btnCancel' => false,
	]);
	$tabControl->End();
	?>
</form>
<script>
(function () {
	var sel = document.getElementById('titlo-api-base-preset');
	var wrap = document.getElementById('titlo-api-base-custom-wrap');
	if (!sel || !wrap) return;
	sel.addEventListener('change', function () {
		wrap.style.display = sel.value === 'custom' ? '' : 'none';
	});
})();
</script>
