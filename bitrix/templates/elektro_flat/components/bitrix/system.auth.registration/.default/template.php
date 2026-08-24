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

<div class="content-form register-form" id="register-form">
	<div class="fields">
		<?if($arResult["USE_EMAIL_CONFIRMATION"] === "Y" && is_array($arParams["AUTH_RESULT"]) &&  $arParams["AUTH_RESULT"]["TYPE"] === "OK"):?>
			<div class="vilmed-form-summary">
				<span class="alertMsg good vilmed-form-alert" role="status">
					<i class="fa fa-check-circle"></i>
					<span class="text"><?echo GetMessage("AUTH_EMAIL_SENT")?></span>
				</span>
			</div>
		<?else:?>
			<?if($arResult["USE_EMAIL_CONFIRMATION"] === "Y"):?>
				<div class="field"><?echo GetMessage("AUTH_EMAIL_WILL_BE_SENT")?></div>
			<?endif?>
			<!--noindex-->
			<form method="post" action="<?=$arResult["AUTH_URL"]?>" name="bform" id="register-form-inner" novalidate>
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
				<input type="hidden" name="AUTH_FORM" value="Y" />
				<input type="hidden" name="TYPE" value="REGISTRATION" />
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_NAME')?>">
					<label class="field-title"><?=GetMessage("AUTH_NAME")?></label>
					<div class="form-input">
						<input type="text" name="USER_NAME" maxlength="50" value="<?=$arResult["USER_NAME"]?>" />
					</div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_NAME')?>
				</div>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_LAST_NAME')?>">
					<label class="field-title"><?=GetMessage("AUTH_LAST_NAME")?></label>
					<div class="form-input">
						<input type="text" name="USER_LAST_NAME" maxlength="50" value="<?=$arResult["USER_LAST_NAME"]?>" />
					</div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_LAST_NAME')?>
				</div>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_LOGIN')?>">
					<label class="field-title"><?=GetMessage("AUTH_LOGIN_MIN")?><span class="starrequired">*</span></label>
					<div class="form-input">
						<input type="text" name="USER_LOGIN" maxlength="50" value="<?=$arResult["USER_LOGIN"]?>" autocomplete="username" />
					</div>
					<div class="description">&mdash; <?=GetMessage("LOGIN_REQUIREMENTS")?></div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_LOGIN')?>
				</div>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_PASSWORD')?>">
					<label class="field-title"><?=GetMessage("AUTH_PASSWORD_REQ")?><span class="starrequired">*</span></label>
					<div class="form-input">
						<input type="password" name="USER_PASSWORD" maxlength="50" value="<?=$arResult["USER_PASSWORD"]?>" autocomplete="new-password" />
					</div>
					<div class="description">&mdash; <?echo $arResult["GROUP_POLICY"]["PASSWORD_REQUIREMENTS"];?></div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_PASSWORD')?>
				</div>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_CONFIRM_PASSWORD')?>">
					<label class="field-title"><?=GetMessage("AUTH_CONFIRM")?><span class="starrequired">*</span></label>
					<div class="form-input">
						<input type="password" name="USER_CONFIRM_PASSWORD" maxlength="50" value="<?=$arResult["USER_CONFIRM_PASSWORD"]?>" autocomplete="new-password" />
					</div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_CONFIRM_PASSWORD')?>
				</div>
				<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'USER_EMAIL')?>">
					<label class="field-title">E-Mail<span class="starrequired">*</span></label>
					<div class="form-input">
						<input type="email" name="USER_EMAIL" maxlength="255" value="<?=$arResult["USER_EMAIL"]?>" autocomplete="email" />
					</div>
					<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'USER_EMAIL')?>
				</div>
				<?//User properties//?>
				<?if($arResult["USER_PROPERTIES"]["SHOW"] == "Y"):?>
					<div class="field"><?=strLen(trim($arParams["USER_PROPERTY_NAME"])) > 0 ? $arParams["USER_PROPERTY_NAME"] : GetMessage("USER_TYPE_EDIT_TAB")?></div>
					<?foreach($arResult["USER_PROPERTIES"]["DATA"] as $FIELD_NAME => $arUserField):?>
						<div class="field">
							<label class="field-title">
								<?=$arUserField["EDIT_FORM_LABEL"]?><?if ($arUserField["MANDATORY"]=="Y"):?><span class="required">*</span><?endif;?>
							</label>
							<div class="form-input">
								<?$APPLICATION->IncludeComponent(
									"bitrix:system.field.edit",
									$arUserField["USER_TYPE"]["USER_TYPE_ID"],
									array("bVarsFromForm" => $arResult["bVarsFromForm"], "arUserField" => $arUserField, "form_name" => "bform"), null, array("HIDE_ICONS"=>"Y"));?>
							</div>
						</div>
					<?endforeach;?>
				<?endif;?>
				<? //CAPTCHA//
				if($arResult["USE_CAPTCHA"] == "Y") {?>
					<div class="field<?=vilmedContentFormFieldClass($vilmedFieldErrors, 'captcha_word')?>">
						<label class="field-title"><?=GetMessage("CAPTCHA_REGF_PROMT")?><span class="starrequired">*</span></label>
						<div class="form-input">
							<input type="text" name="captcha_word" maxlength="50" value="" autocomplete="off" />
							<input type="hidden" name="captcha_sid" value="<?=$arResult["CAPTCHA_CODE"]?>" />
							<img src="/bitrix/tools/captcha.php?captcha_sid=<?=$arResult["CAPTCHA_CODE"]?>" width="127" height="30" alt="CAPTCHA" />
							<div class="clr"></div>
						</div>
						<?=vilmedContentFormFieldErrorHtml($vilmedFieldErrors, 'captcha_word')?>
					</div>
				<?}
				//AGREEMENT//
				if($arSetting["SHOW_PERSONAL_DATA"] == "Y"){?>
					<div class="hint_agreement<?=$vilmedAgreementError ? ' has-error' : ''?>">
						<input type="hidden" name="PERSONAL_DATA" id="PERSONAL_DATA_register" value="N">
						<div class="checkbox">
							<span class="input-checkbox" id="input-checkbox_register"></span>
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
					<button type="submit" id="submit" name="Register" class="btn_buy popdef" value="<?=GetMessage("AUTH_REGISTER")?>"><?=GetMessage("AUTH_REGISTER")?></button>
				</div>
			</form>
			<!--/noindex-->
			<script type="text/javascript">
				document.bform.USER_NAME.focus();
			</script>
		<?endif?>
	</div>
</div>
<script>
	BX.ready(function() {
		var agreementCheckbox = BX("input-checkbox_register");
		if (agreementCheckbox) {
			BX.bind(agreementCheckbox, "click", function() {
				if (!BX.hasClass(agreementCheckbox, "cheked")) {
					BX.addClass(agreementCheckbox, "cheked");
					BX.adjust(agreementCheckbox, {
						children: [BX.create("i", {props: {className: "fa fa-check"}})]
					});
					BX.adjust(BX("PERSONAL_DATA_register"), {props: {"value": "Y"}});
				} else {
					BX.removeClass(agreementCheckbox, "cheked");
					BX.remove(BX.findChild(agreementCheckbox, {className: "fa fa-check"}));
					BX.adjust(BX("PERSONAL_DATA_register"), {props: {"value": "N"}});
				}
			});
		}

		if (window.VilmedContentForm && BX("register-form-inner")) {
			var rules = {
				summaryMessage: "Проверьте выделенные поля",
				fields: [
					{name: "USER_LOGIN", required: true, minLength: 3, message: "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_LOGIN'))?>", minLengthMessage: "<?=CUtil::JSEscape(GetMessage('LOGIN_REQUIREMENTS'))?>"},
					{name: "USER_PASSWORD", required: true, message: "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_PASSWORD'))?>"},
					{name: "USER_CONFIRM_PASSWORD", required: true, message: "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_CONFIRM_PASSWORD'))?>"},
					{name: "USER_EMAIL", required: true, message: "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_EMAIL'))?>"}<?if($arResult["USE_CAPTCHA"] == "Y") {?>,
					{name: "captcha_word", required: true, message: "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_CAPTCHA'))?>"}<?}?>
				],
				confirmPassword: {
					password: "USER_PASSWORD",
					confirm: "USER_CONFIRM_PASSWORD",
					message: "Пароли не совпадают"
				}
			};

			<?if($arSetting["SHOW_PERSONAL_DATA"] == "Y") {?>
			rules.personalDataInput = "#PERSONAL_DATA_register";
			rules.agreement = ".hint_agreement";
			rules.personalDataMessage = "<?=CUtil::JSEscape(GetMessage('NOT_FIELD_PERSONAL_DATA'))?>";
			<?}?>

			window.VilmedContentForm.init("#register-form-inner", rules);
		}
	});
</script>
