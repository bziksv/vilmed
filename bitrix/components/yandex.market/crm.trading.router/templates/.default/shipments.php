<?php

use Bitrix\Main;
use Yandex\Market;

/** @var CMain $APPLICATION */

$APPLICATION->SetAdditionalCSS('/bitrix/panel/main/admin-public.css');

try
{
	$controller = new Market\Ui\Trading\ShipmentList();

	$controller->setTitle();
	$controller->checkReadAccess();
	$controller->loadModules();

	$controller->show();
}
catch (Main\SystemException $exception)
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => $exception->getMessage(),
	]);
}