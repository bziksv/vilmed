<?php
if(isset($_REQUEST['path']) && strlen($_REQUEST['path']) > 0 || !file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/kda.exportexcel/admin/iblock_export_excel_cron_settings.php'))
{
	header((stristr(php_sapi_name(), 'cgi') !== false ? 'Status: ' : $_SERVER['SERVER_PROTOCOL'].' ').'403 Forbidden');
	die();
}
?><?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/kda.exportexcel/admin/iblock_export_excel_cron_settings.php');
?>