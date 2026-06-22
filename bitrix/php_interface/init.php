<?

$isLocalDev = (
	strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
	|| strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false
);

if ($isLocalDev) {
	if (!defined('NO_KEEP_STATISTIC')) define('NO_KEEP_STATISTIC', true);
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
