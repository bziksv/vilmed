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
