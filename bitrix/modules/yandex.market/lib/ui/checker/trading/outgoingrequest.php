<?php

namespace Yandex\Market\Ui\Checker\Trading;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Ui\Checker;
use Yandex\Market\Trading\Setup as TradingSetup;
use Yandex\Market\Trading\Procedure as TradingProcedure;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Settings;

class OutgoingRequest extends Checker\Reference\AbstractTest
{
	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public function test()
	{
		$result = new Market\Result\Base();
		$testPath = 'admin/list';
		$collection = TradingSetup\Collection::loadByFilter([
			'filter' => [ '=ACTIVE' => TradingSetup\Table::BOOLEAN_Y ],
		]);

		/** @var TradingSetup\Model $setup */
		foreach ($collection as $setup)
		{
			try
			{
				$campaignSetup = $this->campaignSetup($setup);

				if ($campaignSetup === null) { continue; }
				if (!$campaignSetup->getService()->getRouter()->hasAction($testPath)) { continue; }

				$procedure = new TradingProcedure\Runner(TradingEntity\Registry::ENTITY_TYPE_ORDER, null);
				$procedure->run($campaignSetup, $testPath, [ 'useCache' => false ]);
			}
			catch (Market\Api\Exception\MethodFailureException $exception)
			{
				continue;
			}
			catch (\Exception $exception)
			{
				$error = $this->makeError($setup, $exception);
				$result->addError($error);
			}
			/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
			catch (\Throwable $exception)
			{
				$error = $this->makeError($setup, $exception);
				$result->addError($error);
			}
		}

		return $result;
	}

	protected function campaignSetup(TradingSetup\Model $trading)
	{
		if ($trading->getBehaviorCode() !== Market\Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			return $trading;
		}

		$business = $trading->getBusiness();
		$knownPlacements = $business->getTradingRepository()->getBusinessPlacements();
		$hasKnownCampaign = false;

		/** @var Campaign\Model $campaign */
		foreach ($trading->getBusiness()->getCampaignCollection() as $campaign)
		{
			if (!in_array($campaign->getPlacement(), $knownPlacements, true)) { continue; }

			if ($campaign->getTradingId() === $trading->getId())
			{
				return $campaign->getTrading();
			}

			if ($campaign->getTradingId() === 0)
			{
				$hasKnownCampaign = true;
			}
		}

		if (!$hasKnownCampaign)
		{
			return null;
		}

		throw new Main\SystemException($this->getMessage('ERROR_MISSING_BUSINESS_CAMPAIGN', [
			'#SETTINGS_URL#' => $this->getSettingsUrl($trading),
		]));
	}

	/**
	 * @param TradingSetup\Model $setup
	 * @param \Exception|\Throwable $exception
	 *
	 * @return Market\Error\Base
	 */
	protected function makeError(TradingSetup\Model $setup, $exception)
	{
		$exceptionMessage = $this->sanitizeExceptionMessage($exception);
		$setupName = $setup->getField('NAME');
		$description = $this->makeExceptionDescription($setup, $exception);

		$result = new Checker\Reference\Error($exceptionMessage);
		$result->setGroup($setupName);
		$result->setDescription($description);

		return $result;
	}

	protected function sanitizeExceptionMessage($exception)
	{
		$messageParts = explode(':', $exception->getMessage(), 2);

		return count($messageParts) === 2 ? $messageParts[1] : $messageParts[0];
	}

	protected function makeExceptionDescription(TradingSetup\Model $setup, $exception)
	{
		if ($exception instanceof Settings\Options\RequiredValueException)
		{
			return $this->getMessage('ERROR_REQUIRED_OPTION_DESCRIPTION', [
				'#SETTINGS_URL#' => $this->getSettingsUrl($setup),
			]);
		}

		if ($exception instanceof Market\Api\Exception\ForbiddenException)
		{
			return $this->getMessage('ERROR_ACCESS_DENIED_DESCRIPTION', [
				'#SETTINGS_URL#' => $this->getSettingsUrl($setup),
			]);
		}

		return null;
	}

	protected function getSettingsUrl(TradingSetup\Model $setup)
	{
		return Market\Ui\Admin\Path::getModuleUrl('trading_edit', [
			'lang' => LANGUAGE_ID,
			'business' => $setup->getBusinessId(),
			'id' => $setup->getId(),
		]);
	}

	protected function getLangPrefix()
	{
		return 'CHECKER_TEST_TRADING_OUTGOING_REQUEST';
	}
}