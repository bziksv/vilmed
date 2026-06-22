<?php

namespace Yandex\Market\Ui\Trading;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Setup as TradingSetup;
use Yandex\Market\Trading\Campaign as TradingCampaign;

class OrderActivity extends Market\Ui\Reference\Page
{
	use Market\Reference\Concerns\HasMessage;

	protected function getReadRights()
	{
		return Market\Ui\Access::RIGHTS_PROCESS_TRADING;
	}

	protected function getWriteRights()
	{
		return Market\Ui\Access::RIGHTS_PROCESS_TRADING;
	}

	public function show()
	{
		$campaign = $this->getCampaign();
		$type = $this->getType();
		list($path) = $this->splitType($type);
		$activity = $this->getActivity($campaign, $type);

		if ($activity instanceof TradingService\Reference\Action\FormActivity)
		{
			$this->showForm($campaign, $path, $activity);
		}
		else if ($activity instanceof TradingService\Reference\Action\ViewActivity)
		{
			$this->showView($campaign, $path, $activity);
		}
		else if ($activity instanceof TradingService\Reference\Action\CommandActivity)
		{
			$commandResult = $this->executeCommand($campaign, $path, $activity);

			$this->sendCommandResponse($commandResult);
		}
	}

	protected function showForm(TradingCampaign\Model $campaign, $path, TradingService\Reference\Action\FormActivity $activity)
	{
		global $APPLICATION;

		$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
			'FORM_ID' => $this->getFormId(),
			'PROVIDER' => Market\Component\TradingActivity\EditForm::class,
			'PRIMARY' => $this->getOrderId(),
			'FIELDS' => $activity->getFields(),
			'ALLOW_SAVE' => $this->isAuthorized($this->getWriteRights()),
			'LAYOUT' => 'raw',
			'GROUP_PRIMARY' => $this->getOrderIds(),
			'TRADING_CAMPAIGN' => $campaign,
			'TRADING_ACTIVITY' => $activity,
			'TRADING_PATH' => $path,
		]);
	}

	protected function showView(TradingCampaign\Model $campaign, $path, TradingService\Reference\Action\ViewActivity $activity)
	{
		global $APPLICATION;

		$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
			'FORM_ID' => $this->getFormId(),
			'PROVIDER' => Market\Component\TradingActivityView\EditForm::class,
			'PRIMARY' => $this->getOrderId(),
			'FIELDS' => $activity->getFields(),
			'LAYOUT' => 'raw',
			'TRADING_CAMPAIGN' => $campaign,
			'TRADING_ACTIVITY' => $activity,
			'TRADING_PATH' => $path,
		]);
	}

	protected function executeCommand(TradingCampaign\Model $campaign, $path, TradingService\Reference\Action\CommandActivity $activity)
	{
		$hasSuccess = false;
		$result = new Main\Result();

		try
		{
			$this->checkSessid();
			$this->checkCommandRequest();

			$setup = $campaign->getTrading();

			foreach ($this->getOrderIds() as $primary)
			{
				$procedureResult = $this->runProcedure($setup, $path, $activity->getPayload(), $primary);

				if ($procedureResult->isSuccess())
				{
					$hasSuccess = true;
				}
				else
				{
					$result->addErrors($procedureResult->getErrors());
				}
			}

			if ($hasSuccess)
			{
				Market\Trading\State\SessionCache::releaseByType('order');
			}
		}
		catch (\Exception $exception)
		{
			$result->addError(new Main\Error($exception->getMessage()));
		}

		return $result;
	}

	protected function runProcedure(TradingSetup\Model $setup, $path, $payload, $orderId)
	{
		$result = new Main\Result();
		$procedure = null;

		try
		{
			$tradingInfo = $this->getTradingInfo($setup, $orderId);
			$payload += $this->getTradingPayload($tradingInfo);

			$procedure = new Market\Trading\Procedure\Runner(
				Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				$tradingInfo['ACCOUNT_NUMBER']
			);

			$procedure->run($setup, $path, $payload);
		}
		catch (Market\Exceptions\Api\Request $exception)
		{
			$exceptionMessage = $exception->getMessage();
			$message = self::getMessage('PROCEDURE_ERROR', [
				'#ORDER_ID#' => $orderId,
				'#MESSAGE#' => $exceptionMessage,
			], $exceptionMessage);

			$result->addError(new Main\Error($message));
		}
		catch (Market\Exceptions\Trading\NotImplementedAction $exception)
		{
			throw $exception;
		}
		catch (\Exception $exception)
		{
			if ($procedure !== null)
			{
				$procedure->logException($exception);
			}

			throw $exception;
		}

		return $result;
	}

	protected function checkCommandRequest()
	{
		if ($this->request->getPost('command') !== 'Y')
		{
			throw new Main\ArgumentException('missing command request');
		}
	}

	protected function checkSessid()
	{
		if (!check_bitrix_sessid())
		{
			throw new Main\ArgumentException('session expired');
		}
	}

	protected function getTradingInfo(TradingSetup\Model $setup, $primary)
	{
		$platform = $setup->getPlatform();
		$orderRegistry = $setup->getEnvironment()->getOrderRegistry();

		return [
			'INTERNAL_ORDER_ID' => $orderRegistry->search($primary, $platform, false),
			'EXTERNAL_ORDER_ID' => $primary,
			'ACCOUNT_NUMBER' => $orderRegistry->search($primary, $platform),
		];
	}

	protected function getTradingPayload(array $tradingInfo)
	{
		return [
			'internalId' => $tradingInfo['INTERNAL_ORDER_ID'],
			'orderId' => $tradingInfo['EXTERNAL_ORDER_ID'],
			'orderNum' => $tradingInfo['ACCOUNT_NUMBER'],
			'immediate' => true,
		];
	}

	protected function sendCommandResponse(Main\Result $commandResult)
	{
		Market\Utils\HttpResponse::sendJson([
			'status' => $commandResult->isSuccess() ? 'ok' : 'error',
			'message' => !$commandResult->isSuccess() ? implode('<br />', $commandResult->getErrorMessages()) : '',
		]);
	}

	protected function getCampaign()
	{
		$id = $this->request->get('campaign');

		Assert::notNull($id, 'campaign');
		Assert::positiveInteger($id, 'campaign');

		return Market\Trading\Campaign\Model::loadById((int)$id);
	}

	protected function getActivity(TradingCampaign\Model $campaign, $path)
	{
		$setup = $campaign->getTrading();
		$environment = $setup->getEnvironment();

		return $setup->getService()->getRouter()->getActivity($path, $environment);
	}

	protected function getType()
	{
		$result = $this->request->get('type');
		Assert::notNull($result, 'type');

		return (string)$result;
	}

	protected function splitType($type)
	{
		return explode('|', $type, 2);
	}

	protected function getFormId()
	{
		$type = $this->getType();
		$type = str_replace('/', '_', $type);
		$type = Market\Data\TextString::toUpper($type);

		return 'YANDEX_MARKET_ADMIN_ORDER_ACTIVITY_' . $type;
	}

	protected function getOrderIds()
	{
		$result = $this->request->get('id');

		Assert::notNull($result, 'id');

		return is_array($result) ? $result : [ $result ];
	}

	protected function getOrderId()
	{
		$ids = $this->getOrderIds();
		$result = reset($ids) ?: null;

		Assert::notNull($result, 'id');

		return $result;
	}
}