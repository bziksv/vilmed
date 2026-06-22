<?php

use Bitrix\Main;
use Yandex\Market;

if (isset($_POST['ajaxReloader']))
{
	define('BX_SECURITY_SESSION_READONLY', true);
	define('BX_SESSION_ID_CHANGE', false);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

$controller = null;

try
{
	if (!Main\Loader::includeModule('yandex.market'))
	{
		throw new Main\SystemException('Module yandex.market required');
	}

	if (isset($_POST['ajaxReloader']))
	{
		session_write_close();
	}

	$controller = new Market\Ui\Trading\SetupEdit();

	$controller->setTitle();
	$controller->checkReadAccess();
	$controller->loadModules();

	if ($controller->hasRequest())
	{
		$controller->processRequest();
	}

	$controller->show();
}
catch (Main\SystemException $exception)
{
	if ($controller !== null)
	{
		$controller->handleException($exception);
	}
	else
	{
		\CAdminMessage::ShowMessage([
			'TYPE' => 'ERROR',
			'MESSAGE' => $exception->getMessage(),
		]);
	}
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
