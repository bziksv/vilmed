<?php

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Utils\HttpResponse;
use Yandex\Market\Components;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

try
{
    if (!Main\Loader::includeModule('yandex.market'))
    {
        throw new Main\SystemException('Module yandex.market is required');
    }

    require_once './class.php';

    $request = Main\Application::getInstance()->getContext()->getRequest();
	$request->addFilter(new Main\Web\PostDecodeFilter());

    $action = $request->getPost('action');
    $payload = $request->getPost('payload');
    $componentParameters = $request->getPost('componentParameters');
	$apiKey = $request->getPost('apiKey');

	if (!is_string($apiKey)) { $apiKey = null; }
    if (!is_array($componentParameters)) { $componentParameters = []; }

    Assert::nonEmptyString($action, 'request[action]');
    Assert::isArray($payload, 'request[payload]');

	$component = new Components\AdminPropertyCategory();
	$property = $component->property($componentParameters);
    $method = $action . 'Action';

	$component->checkAccess($property);

    Assert::methodExists($component, $method);

	if ($apiKey !== null && trim($apiKey) !== '')
	{
		$property['API_KEY'] = trim($apiKey);
	}

    $responseData = $component->$method($payload, $property);

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
