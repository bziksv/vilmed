<?php
define('NOT_CHECK_FILE_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_KEEP_STATISTIC', 'Y');
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;

Loader::includeModule("iblock");

$ID = $_POST["ID"];
$WITHOUT_PROD = $_POST["WITHOUT_PROD"] == 'true' ? true : false;

echo (new CIBlockSection)->Update($ID, ["UF_WITHOUT_PROD" => $WITHOUT_PROD]); 

