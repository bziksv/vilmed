<?php

namespace Yandex\Market\Api\Model;

use Yandex\Market;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Trading\Service\Common\Options;
use Bitrix\Main;

class OutletFacade
{
	use Market\Reference\Concerns\HasLang;

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public static function loadList(Options $options, array $parameters = null, LoggerInterface $logger = null)
	{
		$request = new Market\Api\Partner\Outlets\Request($options->getCampaignId(), $options->getApiAuth(), $logger);

		if ($parameters !== null)
		{
			$request->processParameters($parameters);
		}

		$sendResult = $request->send();

		if (!$sendResult->isSuccess())
		{
			$errorMessage = implode(PHP_EOL, $sendResult->getErrorMessages());
			$exceptionMessage = static::getLang('API_OUTLETS_FETCH_FAILED', [ '#MESSAGE#' => $errorMessage ]);

			throw new Main\SystemException($exceptionMessage);
		}

		/** @var $response Market\Api\Partner\Outlets\Response */
		$response = $sendResult->getResponse();

		return $response->getOutletCollection();
	}

	public static function load(Options $options, $outletId, LoggerInterface $logger = null)
	{
		$request = new Market\Api\Partner\Outlet\Request($options->getCampaignId(), $options->getApiAuth(), $logger);
		$request->setOutletId($outletId);

		$sendResult = $request->send();

		if (!$sendResult->isSuccess())
		{
			$errorMessage = implode(PHP_EOL, $sendResult->getErrorMessages());
			$exceptionMessage = static::getLang('API_OUTLET_FETCH_FAILED', [ '#MESSAGE#' => $errorMessage ], $errorMessage);

			throw new Market\Exceptions\Api\Request($exceptionMessage);
		}

		/** @var $response Market\Api\Partner\Outlet\Response */
		$response = $sendResult->getResponse();

		return $response->getOutlet();
	}
}