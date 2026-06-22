<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/altop_ajax_safe.php";

if(!CModule::IncludeModule("sale") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("iblock"))
	return;

if(!check_bitrix_sessid())
	return;

if(intval($_REQUEST["ID"]) <= 0)
	return;

$qnt = floatval($_REQUEST["quantity"]);

$arItemParams = array();
if(isset($_REQUEST["PROPS"]) && !empty($_REQUEST["PROPS"])) {
	$decoded = altopAjaxSafeDecode($_REQUEST["PROPS"]);
	if (is_array($decoded)) {
		foreach (altopAjaxSanitizeBasketProps($decoded) as $arProp) {
			$arItemParams[] = $arProp;
		}
	}
}
if(isset($_REQUEST["SELECT_PROPS"]) && !empty($_REQUEST["SELECT_PROPS"])) {
	$select_props = explode("||", (string)$_REQUEST["SELECT_PROPS"]);
	foreach($select_props as $arSelProp) {
		$decoded = altopAjaxSafeDecode($arSelProp);
		if (is_array($decoded)) {
			foreach (altopAjaxSanitizeBasketProps([$decoded]) as $arProp) {
				$arItemParams[] = $arProp;
			}
		}
	}
}

$arFields = array("QUANTITY" => $qnt, "DELAY" => "N");

$resBasket = CSaleBasket::GetList(
	array(), 
	array(
		"PRODUCT_ID" => intval($_REQUEST["ID"]),
		"FUSER_ID" => CSaleBasket::GetBasketUserID(),
		"LID" => SITE_ID,
		"ORDER_ID" => "NULL",
		"DELAY" => "Y"
	), 
	false, 
	false, 
	array("ID")
);

if($ar = $resBasket->Fetch()):
	CSaleBasket::Update($ar["ID"], $arFields);
else:
	Add2BasketByProductID(intval($_REQUEST["ID"]), $qnt, $arItemParams);
endif;?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>
