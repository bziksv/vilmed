<?php
if(isset($_REQUEST['path']) && strlen($_REQUEST['path']) > 0 || !file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/esol.exportxml/admin/export_xml_cron_settings.php'))
{
	header((stristr(php_sapi_name(), 'cgi') !== false ? 'Status: ' : $_SERVER['SERVER_PROTOCOL'].' ').'403 Forbidden');
	die();
}
?><?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/esol.exportxml/admin/export_xml_cron_settings.php');
?>