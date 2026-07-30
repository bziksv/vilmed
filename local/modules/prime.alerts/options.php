<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Prime\Alerts\EmailPolicy;

/** @global CMain $APPLICATION */
/** @global CUser $USER */

$moduleId = 'prime.alerts';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
	return;
}

Loader::includeModule($moduleId);

$note = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
	$boolKeys = [
		'enabled',
		'policy_enabled',
		'policy_register',
		'policy_order',
		'notice_everywhere',
	];
	foreach ($boolKeys as $key) {
		Option::set($moduleId, $key, !empty($_POST[$key]) && $_POST[$key] === 'Y' ? 'Y' : 'N');
	}

	$strKeys = [
		'support_email',
		'support_phone',
		'extra_domains',
		'notice_title_signup',
		'notice_title_checkout',
		'error_text_signup',
		'error_text_checkout',
	];
	foreach ($strKeys as $key) {
		Option::set($moduleId, $key, trim((string)($_POST[$key] ?? '')));
	}

	// HTML bodies — keep markup, trim outer whitespace only
	Option::set($moduleId, 'notice_text_signup', trim((string)($_POST['notice_text_signup'] ?? '')));
	Option::set($moduleId, 'notice_text_checkout', trim((string)($_POST['notice_text_checkout'] ?? '')));

	$note = Loc::getMessage('PRIME_ALERTS_SAVED');
}

$aTabs = [
	[
		'DIV' => 'edit1',
		'TAB' => Loc::getMessage('PRIME_ALERTS_TAB'),
		'TITLE' => Loc::getMessage('PRIME_ALERTS_TAB_TITLE'),
	],
];

$tabControl = new CAdminTabControl('primeAlertsTabControl', $aTabs);

if ($note !== '') {
	CAdminMessage::ShowNote($note);
}

$get = static function (string $name, string $default = '') use ($moduleId): string {
	return (string) Option::get($moduleId, $name, $default);
};

$checked = static function (string $name, string $default = 'N') use ($get): string {
	return $get($name, $default) === 'Y' ? ' checked' : '';
};

$noticeTitleSignup = $get('notice_title_signup');
$noticeTextSignup = $get('notice_text_signup');
$noticeTitleCheckout = $get('notice_title_checkout');
$noticeTextCheckout = $get('notice_text_checkout');
$errorSignup = $get('error_text_signup');
$errorCheckout = $get('error_text_checkout');

if ($noticeTitleSignup === '') {
	$noticeTitleSignup = EmailPolicy::getDefaultNoticeTitle('signup');
}
if ($noticeTextSignup === '') {
	$noticeTextSignup = EmailPolicy::getDefaultNoticeText('signup');
}
if ($noticeTitleCheckout === '') {
	$noticeTitleCheckout = EmailPolicy::getDefaultNoticeTitle('checkout');
}
if ($noticeTextCheckout === '') {
	$noticeTextCheckout = EmailPolicy::getDefaultNoticeText('checkout');
}
if ($errorSignup === '') {
	$errorSignup = EmailPolicy::getDefaultErrorText('signup');
}
if ($errorCheckout === '') {
	$errorCheckout = EmailPolicy::getDefaultErrorText('checkout');
}
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>
	<?php $tabControl->Begin(); ?>
	<?php $tabControl->BeginNextTab(); ?>

	<tr>
		<td width="40%"><?= Loc::getMessage('PRIME_ALERTS_ENABLED') ?>:</td>
		<td width="60%"><input type="checkbox" name="enabled" value="Y"<?= $checked('enabled', 'Y') ?>></td>
	</tr>

	<tr class="heading"><td colspan="2">Политика e-mail</td></tr>
	<tr>
		<td valign="top">
			<?= Loc::getMessage('PRIME_ALERTS_POLICY_ENABLED') ?>:<br>
			<small><?= Loc::getMessage('PRIME_ALERTS_POLICY_ENABLED_HINT') ?></small>
		</td>
		<td valign="top"><input type="checkbox" name="policy_enabled" value="Y"<?= $checked('policy_enabled', 'Y') ?>></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('PRIME_ALERTS_POLICY_REGISTER') ?>:</td>
		<td><input type="checkbox" name="policy_register" value="Y"<?= $checked('policy_register', 'Y') ?>></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('PRIME_ALERTS_POLICY_ORDER') ?>:</td>
		<td><input type="checkbox" name="policy_order" value="Y"<?= $checked('policy_order', 'Y') ?>></td>
	</tr>
	<tr>
		<td valign="top">
			<?= Loc::getMessage('PRIME_ALERTS_NOTICE_EVERYWHERE') ?>:<br>
			<small><?= Loc::getMessage('PRIME_ALERTS_NOTICE_EVERYWHERE_HINT') ?></small>
		</td>
		<td valign="top"><input type="checkbox" name="notice_everywhere" value="Y"<?= $checked('notice_everywhere', 'N') ?>></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('PRIME_ALERTS_SUPPORT_EMAIL') ?>:</td>
		<td><input type="text" name="support_email" size="40" value="<?= htmlspecialcharsbx($get('support_email', 'info@kosmamed.ru')) ?>"></td>
	</tr>
	<tr>
		<td><?= Loc::getMessage('PRIME_ALERTS_SUPPORT_PHONE') ?>:</td>
		<td><input type="text" name="support_phone" size="40" value="<?= htmlspecialcharsbx($get('support_phone', '8-800-100-37-97')) ?>"></td>
	</tr>
	<tr>
		<td valign="top">
			<?= Loc::getMessage('PRIME_ALERTS_EXTRA_DOMAINS') ?>:<br>
			<small><?= Loc::getMessage('PRIME_ALERTS_EXTRA_DOMAINS_HINT') ?></small>
		</td>
		<td valign="top">
			<input type="text" name="extra_domains" size="50" value="<?= htmlspecialcharsbx($get('extra_domains')) ?>">
		</td>
	</tr>

	<tr class="heading"><td colspan="2"><?= Loc::getMessage('PRIME_ALERTS_TEXTS') ?></td></tr>
	<tr>
		<td colspan="2"><small><?= Loc::getMessage('PRIME_ALERTS_MACROS_HINT') ?></small></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_NOTICE_TITLE_SIGNUP') ?>:</td>
		<td><input type="text" name="notice_title_signup" size="70" value="<?= htmlspecialcharsbx($noticeTitleSignup) ?>"></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_NOTICE_TEXT_SIGNUP') ?>:</td>
		<td><textarea name="notice_text_signup" cols="70" rows="10"><?= htmlspecialcharsbx($noticeTextSignup) ?></textarea></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_NOTICE_TITLE_CHECKOUT') ?>:</td>
		<td><input type="text" name="notice_title_checkout" size="70" value="<?= htmlspecialcharsbx($noticeTitleCheckout) ?>"></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_NOTICE_TEXT_CHECKOUT') ?>:</td>
		<td><textarea name="notice_text_checkout" cols="70" rows="10"><?= htmlspecialcharsbx($noticeTextCheckout) ?></textarea></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_ERROR_SIGNUP') ?>:</td>
		<td><textarea name="error_text_signup" cols="70" rows="3"><?= htmlspecialcharsbx($errorSignup) ?></textarea></td>
	</tr>
	<tr>
		<td valign="top"><?= Loc::getMessage('PRIME_ALERTS_ERROR_CHECKOUT') ?>:</td>
		<td><textarea name="error_text_checkout" cols="70" rows="3"><?= htmlspecialcharsbx($errorCheckout) ?></textarea></td>
	</tr>

	<?php $tabControl->Buttons(); ?>
	<input type="submit" name="save" value="Сохранить" class="adm-btn-save">
	<?php $tabControl->End(); ?>
</form>
