<?php
/**
 * Безопасное декодирование legacy base64+serialize для AJAX altop-компонентов.
 */

function altopAjaxSafeDecode($encoded)
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
	if (!altopAjaxArrayHasNoObjects($data)) {
		return null;
	}
	return $data;
}

function altopAjaxArrayHasNoObjects(array $arr)
{
	foreach ($arr as $v) {
		if (is_object($v) || is_resource($v)) {
			return false;
		}
		if (is_array($v) && !altopAjaxArrayHasNoObjects($v)) {
			return false;
		}
	}
	return true;
}

function altopAjaxIsValidPropertyCode($code)
{
	return is_string($code) && $code !== "" && (bool)preg_match("/^[A-Za-z0-9_]{1,50}$/", $code);
}

function altopAjaxGetPhoneValidateMask()
{
	$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
	$mask = $request->getCookie("GEOLOCATION_VALIDATE_PHONE_MASK");
	if (!empty($mask) && is_string($mask)) {
		return $mask;
	}
	if (\Bitrix\Main\Loader::includeModule("altop.elektroinstrument")) {
		$arSetting = \CElektroinstrument::GetFrontParametrsValues(SITE_ID);
		if (!empty($arSetting["FORMS_VALIDATE_PHONE_MASK"])) {
			return (string)$arSetting["FORMS_VALIDATE_PHONE_MASK"];
		}
	}
	return "";
}

/**
 * @return array|null ID, CODE, NAME, PROPERTIES[]
 */
function altopAjaxLoadIblockFormData($iblockId)
{
	$iblockId = (int)$iblockId;
	if ($iblockId <= 0) {
		return null;
	}
	$arIblock = CIBlock::GetList(["SORT" => "ASC"], ["ID" => $iblockId, "ACTIVE" => "Y"])->Fetch();
	if (empty($arIblock)) {
		return null;
	}
	$iblock = [
		"ID" => (int)$arIblock["ID"],
		"CODE" => $arIblock["CODE"],
		"NAME" => $arIblock["NAME"],
		"PROPERTIES" => [],
	];
	// PHP 8: nested OR-filter is passed to mysqli_real_escape_string → TypeError (AJAX returns HTML → «Ошибка соединения»).
	$rsProps = CIBlock::GetProperties(
		$iblockId,
		["SORT" => "ASC", "NAME" => "ASC"],
		["ACTIVE" => "Y"]
	);
	while ($arProps = $rsProps->fetch()) {
		if (($arProps["PROPERTY_TYPE"] ?? "") === "S" || ($arProps["PROPERTY_TYPE"] ?? "") === "F") {
			$iblock["PROPERTIES"][] = $arProps;
		}
	}
	if (empty($iblock["PROPERTIES"])) {
		return null;
	}
	return $iblock;
}

function altopAjaxMakeFileFromPosted(array $arFile)
{
	$fileId = (int)($arFile["id"] ?? 0);
	if ($fileId <= 0) {
		return null;
	}
	$fileInfo = CFile::GetFileArray($fileId);
	if (empty($fileInfo)) {
		return null;
	}
	return CFile::MakeFileArray($fileId);
}

function altopAjaxSanitizeBasketProps(array $props)
{
	$result = [];
	foreach ($props as $prop) {
		if (!is_array($prop)) {
			continue;
		}
		if (!altopAjaxArrayHasNoObjects($prop)) {
			continue;
		}
		$clean = [];
		foreach ($prop as $k => $v) {
			if (!is_string($k) && !is_int($k)) {
				continue;
			}
			if (is_array($v)) {
				if (!altopAjaxArrayHasNoObjects($v)) {
					continue;
				}
				$clean[$k] = $v;
			} elseif (is_string($v) || is_int($v) || is_float($v) || is_bool($v) || $v === null) {
				$clean[$k] = $v;
			}
		}
		if (!empty($clean)) {
			$result[] = $clean;
		}
	}
	return $result;
}

/** Расширения реквизитов в «Купить в 1 клик». Список только на сервере. */
function altopAjaxBocAllowedExtensions()
{
	return array("pdf", "doc", "docx", "txt", "rtf", "odt", "ods", "odp");
}

/**
 * Файл реквизитов: только документы из белого списка, без исполняемых и скриптов.
 * @param array $fileArray результат CFile/makeFileArray: name, tmp_name, size
 */
function altopAjaxBocFileAllowed(array $fileArray)
{
	$name = str_replace("\0", "", (string)($fileArray["name"] ?? ""));
	$base = basename(str_replace("\\", "/", $name));
	if ($base === "" || strpos($base, ".") === false) {
		return false;
	}
	if (preg_match('/\.(php\d*|phtml|phar|exe|dll|so|bat|cmd|com|scr|js|mjs|vbs|jse|wsf|jsp|asp|aspx|cgi|pl|py|htaccess|svg|html?|shtml|msi|jar|ps1)(\.|$)/i', $base)) {
		return false;
	}
	$ext = strtolower((string)pathinfo($base, PATHINFO_EXTENSION));
	if (!in_array($ext, altopAjaxBocAllowedExtensions(), true)) {
		return false;
	}
	$tmp = (string)($fileArray["tmp_name"] ?? "");
	if ($tmp === "" || !is_file($tmp)) {
		return false;
	}
	$size = filesize($tmp);
	if ($size === false || $size <= 0 || $size > 20 * 1024 * 1024) {
		return false;
	}
	$fh = fopen($tmp, "rb");
	if (!$fh) {
		return false;
	}
	$head = fread($fh, 16);
	fclose($fh);
	if (!is_string($head) || $head === "") {
		return false;
	}
	if (strncmp($head, "MZ", 2) === 0 || strncmp($head, "\x7fELF", 4) === 0 || strncmp($head, "<?", 2) === 0) {
		return false;
	}
	if ($ext === "pdf") {
		return strncmp($head, "%PDF", 4) === 0;
	}
	if ($ext === "docx" || $ext === "odt" || $ext === "ods" || $ext === "odp") {
		return strncmp($head, "PK\x03\x04", 4) === 0;
	}
	if ($ext === "rtf") {
		return strncmp($head, '{\rtf', 5) === 0;
	}
	if ($ext === "doc") {
		return strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0 || strncmp($head, '{\rtf', 5) === 0;
	}
	return strpos($head, "\0") === false;
}
