<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER["DOCUMENT_ROOT"] . "/include/altop_ajax_safe.php";

if(!CModule::IncludeModule("sale") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("iblock")) {
	return;
}

if(!check_bitrix_sessid())
	return;

if(intval($_REQUEST["id"]) <= 0) {
	return;
}

$qnt = floatval($_REQUEST["qnt"]);

$arItemParams = array();
if(isset($_REQUEST["props"]) && !empty($_REQUEST["props"])) {
	$decoded = altopAjaxSafeDecode($_REQUEST["props"]);
	if (is_array($decoded)) {
		foreach (altopAjaxSanitizeBasketProps($decoded) as $arProp) {
			$arItemParams[] = $arProp;
		}
	}
}
if(isset($_REQUEST["select_props"]) && !empty($_REQUEST["select_props"])) {
	$select_props = explode("||", (string)$_REQUEST["select_props"]);
	foreach($select_props as $arSelProp) {
		$decoded = altopAjaxSafeDecode($arSelProp);
		if (is_array($decoded)) {
			foreach (altopAjaxSanitizeBasketProps([$decoded]) as $arProp) {
				$arItemParams[] = $arProp;
			}
		}
	}
}

$arFields = array("DELAY" => "Y");

$resBasket = CSaleBasket::GetList(
	array(), 
	array(
		"PRODUCT_ID" => intval($_REQUEST["id"]),
		"FUSER_ID" => CSaleBasket::GetBasketUserID(),
		"LID" => SITE_ID,
		"ORDER_ID" => "NULL"
	), 
	false, 
	false, 
	array("ID")
);

if($ar = $resBasket->Fetch()):
	CSaleBasket::Update($ar["ID"], $arFields);
else:
	Add2BasketByProductID(intval($_REQUEST["id"]), $qnt, $arItemParams);
	$resBasket2 = CSaleBasket::GetList(
		array(), 
		array(
			"PRODUCT_ID" => intval($_REQUEST["id"]),
			"FUSER_ID" => CSaleBasket::GetBasketUserID(),
			"LID" => SITE_ID,
			"ORDER_ID" => "NULL"
		), 
		false, 
		false, 
		array("ID")
	);
	while($ar2 = $resBasket2->Fetch()) {
		CSaleBasket::Update($ar2["ID"], $arFields);
	}
endif;?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>
