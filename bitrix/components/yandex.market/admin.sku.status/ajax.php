<?php

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Utils\HttpResponse;
use Yandex\Market\Components;

const BX_SESSION_ID_CHANGE = false;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

session_write_close();

try
{
	if (!Main\Loader::includeModule('yandex.market'))
	{
		throw new Main\SystemException('Module yandex.market is required');
	}

	require_once __DIR__ . '/class.php';

	$request = Main\Application::getInstance()->getContext()->getRequest();
	$request->addFilter(new Main\Web\PostDecodeFilter());

	$ids = $request->getPost('id');
	$iblockId = $request->getPost('iblockId');

	Assert::isArray($ids, 'id');
	Assert::positiveInteger($iblockId, 'iblockId');

	$component = new Components\AdminSkuStatus();
	$responseData = $component->loadAction((int)$iblockId, $ids);

	$response = [
		'status' => 'ok',
		'data' => $responseData,
	];
}
catch (\Exception $e)
{
	if (!($e instanceof Main\SystemException))
	{
		Main\Application::getInstance()->getExceptionHandler()->writeToLog($e);
	}

	$response = [
		'status' => 'error',
		'message' => $e->getMessage(),
	];
}
	/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
catch (\Throwable $e)
{
	Main\Application::getInstance()->getExceptionHandler()->writeToLog($e);

	$response = [
		'status' => 'error',
		'message' => $e->getMessage(),
	];
}

HttpResponse::sendJson($response);
