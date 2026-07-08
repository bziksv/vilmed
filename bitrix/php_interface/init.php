<?

$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalDev = (
	strpos($httpHost, 'localhost') !== false
	|| strpos($httpHost, '127.0.0.1') !== false
	|| (strpos($httpHost, 'vilmed.ru') !== false && strpos($httpHost, '8082') !== false)
);

if ($isLocalDev) {
	if (!defined('NO_KEEP_STATISTIC')) define('NO_KEEP_STATISTIC', true);
	// Не слать почту и не гонять агенты с хитов (на prod — cron + BX_CRONTAB_SUPPORT в dbconn.php)
	if (!defined('DisableEventsCheck')) define('DisableEventsCheck', true);
	if (!defined('BX_CRONTAB_SUPPORT')) define('BX_CRONTAB_SUPPORT', true);
	// Левое меню на /product/: на prod ONE_LEVELS + отдельный кеш; локально — как в каталоге (TWO_LEVELS)
	if (!defined('VILMED_LOCAL_FULL_MENU')) define('VILMED_LOCAL_FULL_MENU', true);
}

AddEventHandler('iblock', 'OnAfterIBlockElementUpdate', 'vilmedClearMenuCacheOnCatalogChange');
AddEventHandler('iblock', 'OnAfterIBlockElementAdd', 'vilmedClearMenuCacheOnCatalogChange');
AddEventHandler('iblock', 'OnAfterIBlockElementDelete', 'vilmedClearMenuCacheOnCatalogChange');
function vilmedClearMenuCacheOnCatalogChange(&$arFields)
{
	if ((int)($arFields['IBLOCK_ID'] ?? 0) !== 24) {
		return;
	}
	if (class_exists('CBitrixComponent')) {
		CBitrixComponent::clearComponentCache('bitrix:menu');
	}
}

if (strpos($_SERVER['REQUEST_URI'], '/bitrix/') === false) {
	$parts_url = explode("?", $_SERVER['REQUEST_URI']);
	
	$parts_url_0= $parts_url[0];
	$parts_url_1= $parts_url[1];

	if ( $parts_url_0 != strtolower($parts_url_0) ) {
		$scheme = $isLocalDev ? 'http' : 'https';
		if(empty($parts_url_1)){
			LocalRedirect($scheme.'://'.$_SERVER['HTTP_HOST'] . strtolower($parts_url_0), false, 301);
		}else{
			LocalRedirect($scheme.'://'.$_SERVER['HTTP_HOST'] . strtolower($parts_url_0).'?'.$parts_url_1, false, 301);
		}
		exit;
	}
}	

AddEventHandler("sale", "OnOrderNewSendEmail", "bxModifySaleMails");
function bxModifySaleMails($orderID, &$eventName, &$arFields)
{	
  if($_COOKIE['roistat_visit'])
    $arFields["ROI_VISIT"] = $_COOKIE['roistat_visit'];
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/catalog_section_list_json.php')){
   require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/catalog_section_list_json.php');
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/functions.php')){
   require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/functions.php');
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/include/vilmed_perf.php')){
   require_once($_SERVER['DOCUMENT_ROOT'].'/include/vilmed_perf.php');
}
