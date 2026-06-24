<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>

<?$APPLICATION->IncludeComponent("bitrix:system.auth.form", "login",
	array(
		"REGISTER_URL" => SITE_DIR."personal/private/",
		"FORGOT_PASSWORD_URL" => SITE_DIR."personal/private/",
		"PROFILE_URL" => SITE_DIR."personal/private/",
		"SHOW_ERRORS" => "N",
	),
	false,
	array("HIDE_ICONS" => "Y")
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>
