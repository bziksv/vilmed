<?php

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
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
    $action = $request->getPost('MASSIVE_ACTION') ?: 'form';
	$method = $action . 'Action';

	$component = new Components\AdminMassiveCategory();
	$component->initComponent('yandex.market:admin.massive.category');

    Assert::methodExists($component, $method);

	foreach ($component->requiredModules() as $moduleName)
	{
		if (!Main\Loader::includeModule($moduleName))
		{
			throw new Main\SystemException("Module {$moduleName} is required");
		}
	}

    $component->$method();
}
catch (\Exception $e)
{
	if (!($e instanceof Main\SystemException))
	{
		Main\Application::getInstance()->getExceptionHandler()->writeToLog($e);
	}

	ShowError($e->getMessage());
}
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
catch (\Throwable $e)
{
	Main\Application::getInstance()->getExceptionHandler()->writeToLog($e);

	ShowError($e->getMessage());
}

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php';
