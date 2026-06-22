<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

//$APPLICATION->ShowAjaxHead();
$APPLICATION->AddHeadScript("/bitrix/js/main/dd.js");

use Bitrix\Main\Application;

$request = Application::getInstance()->getContext()->getRequest();

if(!empty($arParams))
	$arParams = $arParams["PARAMS_STRING"];

$locationId = $request->getCookie("GEOLOCATION_LOCATION_ID");?>

<?
$APPLICATION->IncludeComponent("bitrix:sale.location.selector.search", "geolocation.city",
	array(
		"COMPONENT_TEMPLATE" => ".default",
		"ID" => $locationId,
		"CODE" => "",
		"INPUT_NAME" => "LOCATION",
		"PROVIDE_LINK_BY" => "id",
		"JSCONTROL_GLOBAL_ID" => "",
		"JS_CALLBACK" => "",
		"FILTER_BY_SITE" => "Y",
		"SHOW_DEFAULT_LOCATIONS" => "Y",
		"CACHE_TYPE" => $arParams["CACHE_TYPE"],
		"CACHE_TIME" => $arParams["CACHE_TIME"],
		"FILTER_SITE_ID" => SITE_ID,
		"INITIALIZE_BY_GLOBAL_EVENT" => "",
		"SUPPRESS_ERRORS" => "N"
	),
	false
);?>

<script type="text/javascript">
	//SET_LOCATION//
	BX.SetLocation = function() {
		BX.ajax.post(
			BX.message("GEOLOCATION_COMPONENT_PATH") + "/ajax.php",
			{
				arParams: BX.message("GEOLOCATION_PARAMS"),
				sessid: BX.bitrix_sessid(),
				action: "setLocation",
				locationId: BX.findChild(BX("cityChange"), {tagName: "input", className: "dropdown-field"}, true, false).value
			},
			function(result) {
			    var city = $.cookie('GEOLOCATION_CITY');
			    if (city) {
			        $('#geolocationChangeCity .geolocation__value').text(city);
			    }
                BX.CityChange.popup.close();
			}
		);
	}
	BX.bind(BX("selectCity"), "click", BX.delegate(BX.SetLocation, BX));
</script>
