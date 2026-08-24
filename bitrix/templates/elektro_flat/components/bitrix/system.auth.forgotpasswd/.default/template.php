<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?$arSetting = CElektroinstrument::GetFrontParametrsValues(SITE_ID);
$vilmedFieldErrors = [];
$vilmedGeneralErrors = [];
$vilmedAgreementError = false;
if (function_exists('vilmedParseRegistrationAuthErrors') && is_array($arParams['~AUTH_RESULT']) && ($arParams['~AUTH_RESULT']['TYPE'] ?? '') === 'ERROR') {
	$vilmedParsedErrors = vilmedParseRegistrationAuthErrors($arParams['~AUTH_RESULT']['MESSAGE'] ?? '');
	$vilmedFieldErrors = $vilmedParsedErrors['fields'];
	$vilmedGeneralErrors = $vilmedParsedErrors['general'];
	$vilmedAgreementError = !empty($vilmedParsedErrors['agreement']);
	unset($vilmedFieldErrors['__agreement__']);
}
?>
<div class="content-form forgot-form" id="forgot-form">
	<div class="fields">
		<form name="bform" method="post" target="_top" action="<?=$arResult["AUTH_URL"]?>" id="forgot-form-inner" novalidate>
			<?if(!empty($vilmedGeneralErrors)):?>
				<div class="vilmed-form-summary">
					<span class="alertMsg bad vilmed-form-alert" role="alert">
						<i class="fa fa-exclamation-circle"></i>
						<span class="text"><?=implode('<br>', array_map('htmlspecialcharsbx', $vilmedGeneralErrors))?></span>
					</span>
				</div>
			<?elseif(!empty($vilmedFieldErrors) || $vilmedAgreementError):?>
				<div class="vilmed-form-summary">
					<span class="alertMsg bad vilmed-form-alert" role="alert">
						<i class="fa fa-exclamation-circle"></i>
						<span class="text">Проверьте выделенные поля</span>
					</span>
				</div>
			<?else:?>
				<div class="vilmed-form-summary"></div>
			<?endif?>
			<?if(strlen($arResult["BACKURL"]) > 0) {?>
				<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
			<?}?>
			<input type="hidden" name="AUTH_FORM" value="Y">
			<input type="hidden" name="TYPE" value="SEND_PWD">
			<div class="field">
				<?=GetMessage("AUTH_FORGOT_PASSWORD_1")?>
			</div>
			<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_LOGIN')?>">
				<label class="field-title"><?=GetMessage("AUTH_LOGIN")?></label>
				<div class="form-input">
					<input type="text" name="USER_LOGIN" maxlength="50" value="<?=$arResult["LAST_LOGIN"]?>" autocomplete="username" />
				</div>
				<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_LOGIN')?>
			</div>
			<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_EMAIL')?>">
				<label class="field-title">E-Mail</label>
				<div class="form-input">
					<input type="email" name="USER_EMAIL" maxlength="255" autocomplete="email" />
				</div>
				<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_EMAIL')?>
			</div>
			<?if($arResult["USE_CAPTCHA"]):?>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'captcha_word')?>">
					<label class="field-title"><?=GetMessage("AUTH_CAPTCHA")?></label>
					<div class="form-input">
						<input type="text" name="captcha_word" maxlength="50" value="" autocomplete="off" />
						<input type="hidden" name="captcha_sid" value="<?=$arResult["CAPTCHA_CODE"]?>" />
						<img src="/bitrix/tools/captcha.php?captcha_sid=<?=$arResult["CAPTCHA_CODE"]?>" width="127" height="30" alt="CAPTCHA" />
						<div class="clr"></div>
					</div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'captcha_word')?>
				</div>
			<?endif;?>
			<?if($arSetting["SHOW_PERSONAL_DATA"] == "Y"){?>
				<div id="hint_agreement" class="hint_agreement<?=$vilmedAgreementError ? ' has-error' : ''?>">
					<input type="hidden" name="PERSONAL_DATA" id="PERSONAL_DATA" value="N">
					<div class="checkbox">
						<span class="input-checkbox" id="input-checkbox"></span>
					</div>
					<div class="label">
						<?= function_exists('vilmedLegalFormPersonalDataText') ? vilmedLegalFormPersonalDataText() : $arSetting['TEXT_PERSONAL_DATA'] ?>
					</div>
					<?if($vilmedAgreementError):?>
						<div class="field-error" role="alert"><?=GetMessage("NOT_FIELD_PERSONAL_DATA")?></div>
					<?endif?>
				</div>
			<?}?>
			<div class="field field-button">
				<button type="submit" id="submit" name="send_account_info" class="btn_buy popdef" value="<?=GetMessage("AUTH_SEND")?>"><?=GetMessage("AUTH_SEND")?></button>
			</div>
			<div class="field">
				<a class="btn_buy boc_anch" href="<?=$arResult["AUTH_AUTH_URL"]?>"><i class="fa fa-user"></i><?=GetMessage("AUTH_AUTH")?></a>
			</div>
		</form>
		<script type="text/javascript">
			document.bform.USER_LOGIN.focus();
		</script>
	</div>
</div>
<script>
	BX.ready(function() {
		var agreementCheckbox = BX("input-checkbox");
		if (agreementCheckbox) {
			BX.bind(agreementCheckbox, "click", function() {
				if (!BX.hasClass(agreementCheckbox, "cheked")) {
					BX.addClass(agreementCheckbox, "cheked");
					BX.adjust(agreementCheckbox, {
						children: [BX.create("i", {props: {className: "fa fa-check"}})]
					});
					BX.adjust(BX("PERSONAL_DATA"), {props: {"value": "Y"}});
				} else {
					BX.removeClass(agreementCheckbox, "cheked");
					BX.remove(BX.findChild(agreementCheckbox, {className: "fa fa-check"}));
					BX.adjust(BX("PERSONAL_DATA"), {props: {"value": "N"}});
				}
			});
		}

		if (window.VilmedContentForm && BX("forgot-form-inner")) {
			var rules = {
				summaryMessage: "Проверьте выделенные поля",
				fields: [
					{name: "USER_LOGIN", required: true, message: "Укажите логин"},
					{name: "USER_EMAIL", required: true, message: "Укажите E-mail"}<?if($arResult["USE_CAPTCHA"]):?>,
					{name: "captcha_word", required: true, message: "Введите код с картинки"}<?endif;?>
				]
			};

			<?if($arSetting["SHOW_PERSONAL_DATA"] == "Y") {?>
			rules.personalDataInput = "#PERSONAL_DATA";
			rules.agreement = "#hint_agreement";
			rules.personalDataMessage = "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_PERSONAL_DATA'))?>";
			<?}?>

			window.VilmedContentForm.init("#forgot-form-inner", rules);
		}
	});
</script>
