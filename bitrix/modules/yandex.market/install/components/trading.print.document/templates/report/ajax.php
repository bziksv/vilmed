<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\Access;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading;
use Yandex\Market\Api;
use Yandex\Market\Utils;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loc::loadMessages(__DIR__ . '/template.php');

$moduleLoaded = false;

try
{
	if (!check_bitrix_sessid())
	{
		throw new Main\SystemException(Loc::getMessage('YANDEX_MARKET_T_TRADING_PRINT_REPORT_SESSION_EXPIRED'));
	}

	if (!Main\Loader::includeModule('yandex.market'))
	{
		$message = Loc::getMessage('Module yandex.market required');
		throw new Main\SystemException($message);
	}

	$moduleLoaded = true;

	if (!Access::isProcessTradingAllowed())
	{
		throw new Main\SystemException(Loc::getMessage('YANDEX_MARKET_T_TRADING_PRINT_REPORT_ACCESS_DENIED'));
	}

	$request = Main\Application::getInstance()->getContext()->getRequest();

	$setupId = $request->getPost('setup');
	$reportId = $request->getPost('report');

	Assert::positiveInteger($setupId, 'setup');
	Assert::nonEmptyString($reportId, 'report');

	/** @var Api\Reports\Info\Request $apiRequest */
	$apiRequest = Trading\Setup\Model::loadById($setupId)->wakeupService()->getRequestFactory()->create(Api\Reports\Info\Request::class);
	$apiRequest->setReportId($reportId);

	$apiResponse = $apiRequest->execute();
	$reportStatus = $apiResponse->getStatus();

	if ($reportStatus === Api\Reports\Info\Response::STATUS_DONE)
	{
		$response = [
			'status' => $reportStatus,
			'file' => $apiResponse->getFile(),
		];
	}
	else if ($reportStatus === Api\Reports\Info\Response::STATUS_FAILED)
	{
		$response = [
			'status' => $reportStatus,
			'error' => $apiResponse->textSubStatus(),
		];
	}
	else if (
		$reportStatus === Api\Reports\Info\Response::STATUS_PROCESSING
		|| $reportStatus === Api\Reports\Info\Response::STATUS_PENDING
	)
	{
		$response = [
			'status' => $reportStatus,
			'wait' => max(5000, $apiResponse->getEstimatedGenerationTime()),
			'sessid' => bitrix_sessid(),
		];
	}
	else
	{
		$response = [
			'status' => Api\Reports\Info\Response::STATUS_FAILED,
			'error' => Loc::getMessage('YANDEX_MARKET_T_TRADING_PRINT_REPORT_UNKNOWN_REPORT_STATUS'),
		];
	}
}
catch (Api\Exception\ServerErrorException $exception)
{
	$response = [
		'status' => Api\Reports\Info\Response::STATUS_PENDING,
		'wait' => 10000, // 10 seconds
		'sessid' => bitrix_sessid(),
	];
}
catch (Main\SystemException $exception)
{
	$response = [
		'status' => 'FAILED',
		'error' => $exception->getMessage(),
	];
}

if ($moduleLoaded)
{
	Utils\HttpResponse::sendJson($response);
}
else
{
	echo Main\Web\Json::encode($response);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
