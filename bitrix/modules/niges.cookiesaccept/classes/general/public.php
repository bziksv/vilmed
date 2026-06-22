<?
Class CNigesCookiesAcceptPublic
{
	public static function OnEpilog()
	{
		global $APPLICATION;

		if(isset($_REQUEST['ajax'])){
			$req_ajax = $_REQUEST['ajax'];
		}else{
			$req_ajax = "";
		}

		if (COption::GetOptionString(cookiesaccept_MODULE_ID, "ACTIVE", 'N', SITE_ID) == 'Y'
			&& !$req_ajax
			&& !defined('PUBLIC_AJAX_MODE')
			&& $APPLICATION->buffer_content
		) {
			$APPLICATION->IncludeComponent(
			    str_replace('.', ':', cookiesaccept_MODULE_ID),
			    "",
			    Array(),
			    false,
			    array("HIDE_ICONS" => "Y")
			);
		}
	}
}
?>