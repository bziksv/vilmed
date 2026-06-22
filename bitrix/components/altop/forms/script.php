<?define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/include/altop_ajax_safe.php");

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\Application,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Mail\Event,
	Bitrix\Main\Web\Json;

if(!Loader::IncludeModule("iblock"))
	return;

Loc::loadMessages(__FILE__);

global $APPLICATION;

$request = Application::getInstance()->getContext()->getRequest();
$error = "";

if (!check_bitrix_sessid()) {
	$error .= Loc::getMessage("ERROR_MESSAGE") ? Loc::getMessage("ERROR_MESSAGE")."<br />" : "Session check failed.<br />";
}

$params = altopAjaxSafeDecode($request->getPost("PARAMS_STRING"));
$iblockClient = altopAjaxSafeDecode($request->getPost("IBLOCK_STRING"));

if ($error === "" && (!is_array($params) || !is_array($iblockClient))) {
	$error .= Loc::getMessage("ERROR_MESSAGE") ? Loc::getMessage("ERROR_MESSAGE")."<br />" : "Invalid request data.<br />";
}

$iblock = null;
if ($error === "") {
	$iblockId = (int)($iblockClient["ID"] ?? 0);
	if ($iblockId <= 0) {
		$error .= Loc::getMessage("ERROR_MESSAGE") ? Loc::getMessage("ERROR_MESSAGE")."<br />" : "Invalid iblock.<br />";
	} else {
		$iblock = altopAjaxLoadIblockFormData($iblockId);
		if (!$iblock) {
			$error .= Loc::getMessage("ERROR_MESSAGE") ? Loc::getMessage("ERROR_MESSAGE")."<br />" : "IBlock not found.<br />";
		}
	}
}

if ($error === "" && is_array($params)) {
	$params["VALIDATE_PHONE_MASK"] = altopAjaxGetPhoneValidateMask();
	$params["ELEMENT_NAME"] = mb_substr(strip_tags((string)($params["ELEMENT_NAME"] ?? "")), 0, 500);
	$params["ELEMENT_PRICE"] = mb_substr(strip_tags((string)($params["ELEMENT_PRICE"] ?? "")), 0, 100);
}

$captchaWord = $request->getPost("CAPTCHA_WORD");
$captchaSid = $request->getPost("CAPTCHA_SID");

//REQUARED//
$requared = [];
if ($error === "" && is_array($iblock)) {
	foreach($iblock["PROPERTIES"] as $arProp) {
		if($arProp["CODE"] != "PRODUCT" && $arProp["CODE"] != "PRODUCT_PRICE") {
			if($arProp["IS_REQUIRED"] == "Y") {
				$requared[] = array(
					"CODE" => $arProp["CODE"],
					"NAME" => $arProp["NAME"]
				);
			}
		}
	}
	unset($arProp);
}

//CHECKS//
if($error === "" && !empty($requared)) {
	foreach($requared as $arProp) {
		$post = $request->getPost($arProp["CODE"]);
		if(empty($post))
			$error .= Loc::getMessage("FIELD_NOT_FILLED", array("#FIELD#" => $arProp["NAME"]))."<br />";
	}
	unset($arProp);
}
unset($requared);

//CHECKS_PERSONAL_DATA//
$personalData = $request->getPost("PERSONAL_DATA");
if($error === "" && $personalData === "N") {
	$error .= Loc::getMessage("FIELD_NOT_FILLED_PERSONAL_DATA")."<br />";
}

//VALIDATE_PHONE_MASK//
if ($error === "" && is_array($iblock) && is_array($params) && !empty($params["VALIDATE_PHONE_MASK"])) {
	foreach($iblock["PROPERTIES"] as $arProp) {
		if($arProp["CODE"] == "PHONE") {
			$post = $request->getPost($arProp["CODE"]);
			if(!empty($post)) {
				if(!@preg_match($params["VALIDATE_PHONE_MASK"], $post)) {
					$error .= Loc::getMessage("FIELD_INVALID", array("#FIELD#" => $arProp["NAME"]))."<br />";
				}
			}
		}
	}
	unset($arProp);
}

if($error === "" && !empty($captchaSid) && !$APPLICATION->CaptchaCheckCode($captchaWord, $captchaSid))
	$error .= Loc::getMessage("WRONG_CAPTCHA")."<br />";

if(!empty($error)) {
	$result = array(
		"error" => array(
			"text" => $error,
			"captcha_code" => !empty($captchaSid) ? $APPLICATION->CaptchaGetCode() : ""
		)
	);
	echo Json::encode($result);
	return;
}

//PROPERTIES//
$arProps = $arPropsMess = array();
foreach($iblock["PROPERTIES"] as $arProp) {
	if(!altopAjaxIsValidPropertyCode($arProp["CODE"])) {
		continue;
	}
	if($arProp["CODE"] == "PRODUCT") {
		$arProps[$arProp["CODE"]] = $params["ELEMENT_NAME"];
		$arPropsMess[$arProp["CODE"]] = $params["ELEMENT_NAME"];
	} elseif($arProp["CODE"] == "PRODUCT_PRICE") {
		$arProps[$arProp["CODE"]] = $params["ELEMENT_PRICE"];
		$arPropsMess[$arProp["CODE"]] = $params["ELEMENT_PRICE"];
	} else {
		$post = $request->getPost($arProp["CODE"]);
		if(!empty($post)) {
			if($arProp["PROPERTY_TYPE"] == "S") {
				if($arProp["USER_TYPE"] == "HTML") {
					$arProps[$arProp["CODE"]] = array(
						"VALUE" => array(
							"TEXT" => iconv("UTF-8", SITE_CHARSET, strip_tags(trim($post))),
							"TYPE" => $arProp["DEFAULT_VALUE"]["TYPE"]
						)
					);
				} else {
					$arProps[$arProp["CODE"]] = iconv("UTF-8", SITE_CHARSET, strip_tags(trim($post)));
				}
				$arPropsMess[$arProp["CODE"]] = iconv("UTF-8", SITE_CHARSET, strip_tags(trim($post)));
			} elseif($arProp["PROPERTY_TYPE"] == "F" && is_array($post)) {
				foreach($post as $arFile) {
					if (!is_array($arFile)) {
						continue;
					}
					$fileArray = altopAjaxMakeFileFromPosted($arFile);
					if ($fileArray) {
						$arProps[$arProp["CODE"]][] = $fileArray;
					}
				}
				unset($arFile);
			}
		}
	}
}
unset($arProp);

//NEW_ELEMENT//
$el = new CIBlockElement;

$arFields = array(
	"IBLOCK_ID" => $iblock["ID"],
	"ACTIVE" => "Y",
	"NAME" => Loc::getMessage("IBLOCK_ELEMENT_NAME").ConvertTimeStamp(time(), "FULL"),
	"PROPERTY_VALUES" => $arProps,
);

if($elementId = $el->Add($arFields)) {
	//MAIL_EVENT//	
	$eventName = "ALTOP_FORM_".$iblock["CODE"];

	$eventDesc = $messBody = "";	
	foreach($iblock["PROPERTIES"] as $arProp) {
		if($arProp["PROPERTY_TYPE"] != "F") {
			$eventDesc .= "#".$arProp["CODE"]."# - ".$arProp["NAME"]."\n";
			$messBody .= $arProp["NAME"].": "."#".$arProp["CODE"]."#\n";
		}
	}
	unset($arProp);
	$eventDesc .= Loc::getMessage("MAIL_EVENT_DESCRIPTION");
	
	//MAIL_EVENT_TYPE//
	$arEvent = CEventType::GetByID($eventName, LANGUAGE_ID)->Fetch();
	if(empty($arEvent)) {
		$et = new CEventType;
		$arEventFields = array(
			"LID" => LANGUAGE_ID,
			"EVENT_NAME" => $eventName,
			"NAME" => Loc::getMessage("MAIL_EVENT_TYPE_NAME")." \"".$iblock["NAME"]."\"",
			"DESCRIPTION" => $eventDesc
		);
		$et->Add($arEventFields);		
	}

	//MAIL_EVENT_MESSAGE//
	$arMess = CEventMessage::GetList($by = "site_id", $order = "desc", array("TYPE_ID" => $eventName))->Fetch();
	if(empty($arMess)) {
		$em = new CEventMessage;
		$arMess = array();
		$arMess["ID"] = $em->Add(
			array(
				"ACTIVE" => "Y",
				"EVENT_NAME" => $eventName,
				"LID" => SITE_ID,
				"EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
				"EMAIL_TO" => "#EMAIL_TO#",
				"BCC" => "",
				"SUBJECT" => Loc::getMessage("MAIL_EVENT_MESSAGE_SUBJECT"),
				"BODY_TYPE" => "text",
				"MESSAGE" => Loc::getMessage("MAIL_EVENT_MESSAGE_MESSAGE_HEADER").$messBody.Loc::getMessage("MAIL_EVENT_MESSAGE_MESSAGE_FOOTER")
			)
		);		
	}

	//SEND_MAIL//
	$arPropsMess["FORM_NAME"] = $iblock["NAME"];
	$arPropsMess["ROI_VISIT"] = $_COOKIE['roistat_visit'] ?: "";
	$arPropsMess["EMAIL_TO"] = Option::get("main", "email_from");
	
	$arFiles = array();
	foreach($iblock["PROPERTIES"] as $arProp) {
		if($arProp["PROPERTY_TYPE"] == "F") {			
			$rsProps = CIBlockElement::GetProperty($iblock["ID"], $elementId, "sort", "asc", array("CODE" => $arProp["CODE"]));
			while($obProp = $rsProps->GetNext()) {
				$arFiles[] = $obProp["VALUE"];
			}
			unset($obProp);
		}
	}
	unset($arProp);
	
	Event::send(array(
		"EVENT_NAME" => $eventName,
		"LID" => SITE_ID,
		"C_FIELDS" => $arPropsMess,
		"FILE" => $arFiles
	));
	
	$result = array(
		"success" => array(
			"text" => Loc::getMessage("SUCCESS_MESSAGE")
		)
	);	
} else {	
	$result = array(
		"error" => array(
			"text" => Loc::getMessage("ERROR_MESSAGE")."<br />".$el->LAST_ERROR,
			"captcha_code" => !empty($captchaSid) ? $APPLICATION->CaptchaGetCode() : ""
		)
	);
}

echo Json::encode($result);?>
