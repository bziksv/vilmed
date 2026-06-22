<?php
// Публичный endpoint: форма должна передавать sessid (bitrix_sessid_post() в шаблоне / то же в AJAX).
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Web\Json;

if (!Loader::IncludeModule("iblock")) {
	return;
}

Loc::loadMessages(__FILE__);

global $APPLICATION, $USER;

$request = Application::getInstance()->getContext()->getRequest();

$error = "";

/**
 * Декодирует legacy base64+serialize только в массивы без объектов (защита от object injection).
 */
function altopCatalogReviewsSafeDecode($encoded)
{
	if ($encoded === null || $encoded === "") {
		return null;
	}
	if (!is_string($encoded)) {
		return null;
	}
	$raw = base64_decode(strtr($encoded, "-_,", "+/="), true);
	if ($raw === false || $raw === "") {
		return null;
	}
	$data = @unserialize($raw, ["allowed_classes" => false]);
	if ($data === false && $raw !== serialize(false)) {
		return null;
	}
	if (!is_array($data)) {
		return null;
	}
	if (!altopCatalogReviewsArrayHasNoObjects($data)) {
		return null;
	}
	return $data;
}

function altopCatalogReviewsArrayHasNoObjects(array $arr)
{
	foreach ($arr as $v) {
		if (is_object($v) || is_resource($v)) {
			return false;
		}
		if (is_array($v) && !altopCatalogReviewsArrayHasNoObjects($v)) {
			return false;
		}
	}
	return true;
}

function altopCatalogReviewsIsValidPropertyCode($code)
{
	return is_string($code) && $code !== "" && (bool)preg_match("/^[A-Za-z0-9_]{1,50}$/", $code);
}

/**
 * Разрешаем только относительный путь или URL с тем же host, что у текущего запроса.
 */
function altopCatalogReviewsSanitizeCommentUrl($url)
{
	$url = trim((string)$url);
	if ($url === "" || strlen($url) > 2000) {
		return "";
	}
	if ($url[0] === "/") {
		return $url;
	}
	$parts = parse_url($url);
	if (empty($parts["host"])) {
		return "";
	}
	$reqHost = isset($_SERVER["HTTP_HOST"]) ? strtolower(preg_replace("~:\d+$~", "", (string)$_SERVER["HTTP_HOST"])) : "";
	$urlHost = strtolower(preg_replace("~:\d+$~", "", (string)$parts["host"]));
	if ($reqHost !== "" && $urlHost === $reqHost) {
		return $url;
	}
	return "";
}

if (!check_bitrix_sessid()) {
	$error .= "Session check failed (sessid).<br />";
}

$params = altopCatalogReviewsSafeDecode($request->getPost("PARAMS_STRING"));
$iblockClient = altopCatalogReviewsSafeDecode($request->getPost("IBLOCK_STRING"));
$elementClient = altopCatalogReviewsSafeDecode($request->getPost("ELEMENT_STRING"));

if (!is_array($params) || !is_array($iblockClient) || !is_array($elementClient)) {
	$em = Loc::getMessage("ERROR_MESSAGE");
	$error .= ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "Invalid request data.<br />";
}

$iblockId = 0;
$elementId = 0;
if ($error === "" && is_array($iblockClient) && is_array($elementClient)) {
	$iblockId = (int)($iblockClient["ID"] ?? 0);
	$elementId = (int)($elementClient["ID"] ?? 0);
	if ($iblockId <= 0 || $elementId <= 0) {
		$em = Loc::getMessage("ERROR_MESSAGE");
		$error .= ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "Invalid element or iblock.<br />";
	}
}

$iblock = null;
$element = null;
if ($error === "" && $iblockId > 0 && $elementId > 0) {
	$res = CIBlockElement::GetList(
		[],
		["ID" => $elementId, "IBLOCK_ID" => $iblockId],
		false,
		false,
		["ID", "NAME", "IBLOCK_ID"]
	);
	$element = $res->GetNext();
	if (!$element || (int)$element["IBLOCK_ID"] !== $iblockId) {
		$em = Loc::getMessage("ERROR_MESSAGE");
		$error .= ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "Element not found.<br />";
	} else {
		$iblockRow = CIBlock::GetByID($iblockId)->Fetch();
		if (!$iblockRow) {
			$em = Loc::getMessage("ERROR_MESSAGE");
			$error .= ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "IBlock not found.<br />";
		} else {
			$iblock = [
				"ID" => (int)$iblockRow["ID"],
				"PROPERTIES" => [],
			];
			$dbProps = CIBlockProperty::GetList(["SORT" => "ASC"], ["IBLOCK_ID" => $iblockId, "ACTIVE" => "Y"]);
			while ($p = $dbProps->Fetch()) {
				$iblock["PROPERTIES"][$p["ID"]] = [
					"CODE" => $p["CODE"],
					"NAME" => $p["NAME"],
				];
			}
		}
	}
}

$propertyCodes = [];
if ($error === "" && is_array($params)) {
	$preMod = isset($params["PRE_MODERATION"]) ? (string)$params["PRE_MODERATION"] : "N";
	$params["PRE_MODERATION"] = ($preMod === "Y") ? "Y" : "N";

	$params["COMMENT_URL"] = altopCatalogReviewsSanitizeCommentUrl(isset($params["COMMENT_URL"]) ? (string)$params["COMMENT_URL"] : "");

	if (empty($params["PROPERTIES"]) || !is_array($params["PROPERTIES"])) {
		$em = Loc::getMessage("ERROR_MESSAGE");
		$error .= ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "Invalid properties list.<br />";
	} else {
		$allowedCodes = [];
		foreach ($iblock["PROPERTIES"] as $ap) {
			if (!empty($ap["CODE"])) {
				$allowedCodes[$ap["CODE"]] = true;
			}
		}
		$em = Loc::getMessage("ERROR_MESSAGE");
		$em = ($em !== false && $em !== null && $em !== "") ? $em . "<br />" : "Invalid request.<br />";
		foreach ($params["PROPERTIES"] as $arCode) {
			if (!altopCatalogReviewsIsValidPropertyCode($arCode)) {
				$error .= $em;
				break;
			}
			if (!isset($allowedCodes[$arCode])) {
				$error .= $em;
				break;
			}
			$propertyCodes[] = $arCode;
		}
	}
}

$name = $request->getPost("NAME");
$message = $request->getPost("MESSAGE");

$captchaWord = $request->getPost("CAPTCHA_WORD");
$captchaSid = $request->getPost("CAPTCHA_SID");

if ($error === "") {
	foreach ($propertyCodes as $arCode) {
		$post = $request->getPost($arCode);
		if ($post === null || $post === "" || (is_array($post) && count($post) === 0)) {
			$msg = Loc::getMessage($arCode . "_NOT_FILLED");
			$error .= ($msg !== false && $msg !== null && $msg !== "") ? $msg . "<br />" : "Field not filled: " . htmlspecialcharsbx($arCode) . "<br />";
		}
	}

	if (!empty($captchaSid) && !$APPLICATION->CaptchaCheckCode($captchaWord, $captchaSid)) {
		$error .= Loc::getMessage("WRONG_CAPTCHA") . "<br />";
	}
}

if ($error !== "") {
	$result = [
		"error" => [
			"text" => $error,
			"captcha_code" => !empty($captchaSid) ? $APPLICATION->CaptchaGetCode() : "",
		],
	];
	echo Json::encode($result);
	return;
}

$name = strip_tags(trim((string)$name));
$message = strip_tags(trim((string)$message));
if (function_exists("mb_strlen")) {
	if (mb_strlen($name) > 500) {
		$name = mb_substr($name, 0, 500);
	}
	if (mb_strlen($message) > 10000) {
		$message = mb_substr($message, 0, 10000);
	}
} else {
	if (strlen($name) > 500) {
		$name = substr($name, 0, 500);
	}
	if (strlen($message) > 10000) {
		$message = substr($message, 0, 10000);
	}
}

if (defined("SITE_CHARSET") && SITE_CHARSET && strtoupper(SITE_CHARSET) !== "UTF-8") {
	$nameConv = @iconv("UTF-8", SITE_CHARSET, $name);
	$messageConv = @iconv("UTF-8", SITE_CHARSET, $message);
	if ($nameConv !== false) {
		$name = $nameConv;
	}
	if ($messageConv !== false) {
		$message = $messageConv;
	}
}

$createdBy = (is_object($USER) && method_exists($USER, "GetID")) ? (int)$USER->GetID() : 0;
if ($createdBy <= 0) {
	$createdBy = false;
}

$arProps = [
	"OBJECT_ID" => $element["ID"],
	"USER_ID" => $name,
	"USER_IP" => $_SERVER["REMOTE_ADDR"] ?? "",
	"COMMENT_URL" => $params["COMMENT_URL"],
];

$el = new CIBlockElement;

$arFields = [
	"NAME" => Loc::getMessage("IBLOCK_ELEMENT_NAME") . ConvertTimeStamp(time(), "FULL"),
	"IBLOCK_ID" => $iblock["ID"],
	"ACTIVE" => $params["PRE_MODERATION"] !== "Y" ? "Y" : "N",
	"ACTIVE_FROM" => ConvertTimeStamp(false, "FULL"),
	"DETAIL_TEXT" => $message,
	"CREATED_BY" => $createdBy,
	"PROPERTY_VALUES" => $arProps,
];

if ($el->Add($arFields)) {
	$eventName = "ALTOP_FORM_catalog_review_" . SITE_ID;

	$eventDesc = $messBody = "";
	foreach ($iblock["PROPERTIES"] as $arProp) {
		$eventDesc .= "#" . $arProp["CODE"] . "# - " . $arProp["NAME"] . "\n";
		$messBody .= $arProp["NAME"] . ": " . "#" . $arProp["CODE"] . "#\n";
	}
	$eventDesc .= Loc::getMessage("MAIL_EVENT_DESCRIPTION");
	$messBody .= Loc::getMessage("MAIL_MESSAGE_BODY");

	$arEvent = CEventType::GetByID($eventName, LANGUAGE_ID)->Fetch();
	if (empty($arEvent)) {
		$et = new CEventType();
		$arEventFields = [
			"LID" => LANGUAGE_ID,
			"EVENT_NAME" => $eventName,
			"NAME" => Loc::getMessage("MAIL_EVENT_TYPE_NAME"),
			"DESCRIPTION" => $eventDesc,
		];
		$et->Add($arEventFields);
	}

	$arMess = CEventMessage::GetList($by = "site_id", $order = "desc", ["TYPE_ID" => $eventName])->Fetch();
	if (empty($arMess)) {
		$em = new CEventMessage();
		$em->Add(
			[
				"ACTIVE" => "Y",
				"EVENT_NAME" => $eventName,
				"LID" => SITE_ID,
				"EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
				"EMAIL_TO" => "#EMAIL_TO#",
				"BCC" => "",
				"SUBJECT" => Loc::getMessage("MAIL_EVENT_MESSAGE_SUBJECT"),
				"BODY_TYPE" => "text",
				"MESSAGE" => Loc::getMessage("MAIL_EVENT_MESSAGE_MESSAGE_HEADER") . $messBody . Loc::getMessage("MAIL_EVENT_MESSAGE_MESSAGE_FOOTER"),
			]
		);
	}

	$arProps["OBJECT_ID"] = $element["ID"] . " (" . $element["NAME"] . ")";
	$arProps["MESSAGE"] = $message;
	$arProps["EMAIL_TO"] = Option::get("main", "email_from");

	Event::send([
		"EVENT_NAME" => $eventName,
		"LID" => SITE_ID,
		"C_FIELDS" => $arProps,
	]);

	$result = [
		"success" => [
			"text" => $params["PRE_MODERATION"] !== "Y" ? Loc::getMessage("SUCCESS_MESSAGE") : Loc::getMessage("PRE_MODERATION_MESSAGE"),
		],
	];
} else {
	$result = [
		"error" => [
			"text" => Loc::getMessage("ERROR_MESSAGE") . "<br />" . $el->LAST_ERROR,
			"captcha_code" => !empty($captchaSid) ? $APPLICATION->CaptchaGetCode() : "",
		],
	];
}

echo Json::encode($result);
