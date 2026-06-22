<?php

use Bitrix\Main;
use Yandex\Market;

try
{
	$controller = new Market\Ui\Trading\OrderAdmin();
	$controller->loadModules();
	$controller->checkReadAccess();

	$controller->show();
}
catch (Main\SystemException $exception)
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => $exception->getMessage(),
	]);
}