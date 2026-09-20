<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\AdminUi;
use Titlo\Relevance\Config;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('titlo.relevance');

$APPLICATION->SetTitle('Titlo: полезные сервисы');

$services = Config::cabinetUsefulServices();
$go = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['go'] ?? '')));
$openUrl = '';
if ($go !== '' && isset($services[$go])) {
	$openUrl = $services[$go]['url'];
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

AdminUi::renderCss();
?>

<div class="titlo-box titlo-useful">
	<details class="titlo-howto" open>
		<summary>Что это за сервисы</summary>
		<div class="titlo-howto__body">
			<p style="margin:0">
				В кабинете Titlo есть и другие инструменты помимо генерации текстов.
				Ниже — коротко, зачем каждый. По клику откроется нужный раздел
				(если ещё не вошли в кабинет — сначала попросит авторизацию).
			</p>
		</div>
	</details>

	<div class="titlo-useful__grid">
		<?php foreach ($services as $code => $svc): ?>
			<a class="titlo-useful__card<?= $go === $code ? ' is-target' : '' ?>"
			   href="<?= htmlspecialcharsbx($svc['url']) ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   data-titlo-useful="<?= htmlspecialcharsbx($code) ?>">
				<span class="titlo-useful__icon" aria-hidden="true">
					<img src="<?= htmlspecialcharsbx($svc['icon'] ?? '') ?>" alt="" width="36" height="36">
				</span>
				<strong><?= htmlspecialcharsbx($svc['title']) ?></strong>
				<span><?= htmlspecialcharsbx($svc['hint']) ?></span>
				<em>Открыть в кабинете →</em>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<?php if ($openUrl !== ''): ?>
<script>
(function () {
	var url = <?= json_encode($openUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
	if (!url) return;
	var w = window.open(url, '_blank');
	if (w) {
		try { w.opener = null; } catch (e) {}
	}
})();
</script>
<?php endif; ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
