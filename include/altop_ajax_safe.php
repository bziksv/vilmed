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
			return $arSetting["FORMS_VALIDATE_PHONE_MASK"];
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
	$rsProps = CIBlock::GetProperties(
		$iblockId,
		["SORT" => "ASC", "NAME" => "ASC"],
		["ACTIVE" => "Y", ["LOGIC" => "OR", ["PROPERTY_TYPE" => "S"], ["PROPERTY_TYPE" => "F"]]]
	);
	while ($arProps = $rsProps->fetch()) {
		$iblock["PROPERTIES"][] = $arProps;
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
