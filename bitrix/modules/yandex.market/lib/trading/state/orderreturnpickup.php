<?php
namespace Yandex\Market\Trading\State;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class OrderReturnPickup extends Internals\AgentSkeleton
{
	use Market\Reference\Concerns\HasMessage;

	public static function getDefaultParams()
	{
		return [
			'interval' => static::getPeriod('restart', 86400),
		];
	}

	public static function schedule($campaignId, $orderId)
	{
		static::insertOrder($campaignId, $orderId);
		static::register([
			'method' => 'start',
			'arguments' => [ (int)$campaignId ],
		]);
	}

	protected static function insertOrder($campaignId, $orderId)
	{
		$queryExist = Internals\OrderReturnTable::getList([
			'filter' => [
				'=CAMPAIGN_ID' => $campaignId,
				'=ORDER_ID' => $orderId,
			],
		]);

		if ($queryExist->fetch()) { return; }

		Internals\OrderReturnTable::add([
			'CAMPAIGN_ID' => $campaignId,
			'ORDER_ID' => $orderId,
			'STATUS' => Internals\OrderReturnTable::STATUS_PROCESS,
			'TIMESTAMP_X' => new Market\Data\Type\CanonicalDateTime(),
		]);
	}

	public static function start($campaignId)
	{
		$from = new Market\Data\Type\CanonicalDateTime();

		static::register([
			'method' => 'sync',
			'arguments' => [ $campaignId, $from->format(\DateTime::ATOM) ],
			'interval' => static::getPeriod('step', static::PERIOD_STEP_DEFAULT),
		]);
	}

	public static function sync($campaignId, $startDateString, $offset = null, $pageToken = null, $errorCount = 0)
	{
		return static::wrapAction(
			[static::class, 'syncBody'],
			[ $campaignId, $startDateString, $offset, $pageToken ],
			$errorCount
		);
	}

	protected static function syncBody($campaignId, $startDateString, $offset = null, $pageToken = null)
	{
		$limit = static::getPageSize();
		$scheduled = static::getScheduled($campaignId, $limit, $offset);

		if (empty($scheduled))
		{
			static::finalize($campaignId, $startDateString);
			return false;
		}

		$setup = static::getCampaignSetup($campaignId);
		$service = $setup->wakeupService();
		$accountNumberMap = static::mapOrderAccountNumber($setup, array_column($scheduled, 'ORDER_ID'));

		list($statuses, $errors, $nextPageToken) = static::returnStatuses($service, array_keys($accountNumberMap), $pageToken);

		foreach ($scheduled as $task)
		{
			if (!isset($accountNumberMap[$task['ORDER_ID']]))
			{
				static::commit($setup, $task['ORDER_ID'], Internals\OrderReturnTable::STATUS_FAIL);
				continue;
			}

			if (!isset($statuses[$task['ORDER_ID']])) { continue; }

			$newStatus = $statuses[$task['ORDER_ID']];
			$accountNumber = $accountNumberMap[$task['ORDER_ID']];

			if ($newStatus === Internals\OrderReturnTable::STATUS_SUCCESS)
			{
				static::emulateStatus($setup, $task['ORDER_ID'], $accountNumber);
			}

			if (isset($errors[$task['ORDER_ID']]))
			{
				static::markOrder($setup, $task['ORDER_ID'], $accountNumber, $errors[$task['ORDER_ID']]);
			}

			static::commit($setup, $task['ORDER_ID'], $newStatus);

			if (static::isTimeExpired())
			{
				return [ $campaignId, $startDateString, $task['ORDER_ID'] ];
			}
		}

		if ($nextPageToken)
		{
			return [ $campaignId, $startDateString, $offset, $nextPageToken ];
		}

		if (count($scheduled) < $limit)
		{
			static::finalize($campaignId, $startDateString);
			return false;
		}

		$lastTask = end($scheduled);

		return [ $campaignId, $startDateString, $lastTask['ORDER_ID'] ];
	}

	protected static function finalize($campaignId, $startDateString)
	{
		static::clearScheduled($campaignId, $startDateString);

		if (!static::getScheduled($campaignId, 1))
		{
			static::unregister([
				'method' => 'start',
				'arguments' => [ (int)$campaignId ],
			]);
		}
	}

	protected static function clearScheduled($campaignId, $startDateString)
	{
		$unknownClearDate = new Market\Data\Type\CanonicalDateTime($startDateString, \DateTime::ATOM);
		$unknownClearDate->add('-P2D');

		Internals\OrderReturnTable::deleteBatch([
			'filter' => [
				'=CAMPAIGN_ID' => $campaignId,
				[
					'LOGIC' => 'OR',
					[
						'=STATUS' => [
							Internals\OrderReturnTable::STATUS_SUCCESS,
							Internals\OrderReturnTable::STATUS_FAIL,
						],
					],
					[ '<=TIMESTAMP_X' => $unknownClearDate ],
				],
			],
		]);
	}

	protected static function getScheduled($campaignId, $limit, $offset = null)
	{
		$filter = [
			'=CAMPAIGN_ID' => $campaignId,
			'=STATUS' => Internals\OrderReturnTable::STATUS_PROCESS,
		];

		if ($offset !== null)
		{
			$filter['>ORDER_ID'] = $offset;
		}

		$query = Internals\OrderReturnTable::getList([
			'filter' => $filter,
			'limit' => $limit,
			'order' => [ 'ORDER_ID' => 'asc' ],
		]);

		return $query->fetchAll();
	}

	protected static function mapOrderAccountNumber(Market\Trading\Setup\Model $setup, array $externalIds)
	{
		return $setup->getEnvironment()->getOrderRegistry()->searchList($externalIds, $setup->getPlatform());
	}

	protected static function returnStatuses(TradingService\Reference\Provider $service, array $externalIds, $pageToken = null)
	{
		if (empty($externalIds)) { return [ [], [], null ]; }

		$returnsResponse = static::loadReturns($service, $externalIds, $pageToken);
        $paging = $returnsResponse->getPaging();
		list($statuses, $errors) = static::collectReturnStatuses($returnsResponse->getReturnCollection());

		return [
			$statuses,
			$errors,
            $paging->hasNext() ? $paging->getNextPageToken() : null,
		];
	}

	/**
	 * @param TradingService\Reference\Provider $service
	 * @param string[] $orderIds
	 * @param string|null $pageToken
	 *
	 * @return TradingService\Marketplace\Api\Returns\Response
	 */
	protected static function loadReturns(TradingService\Reference\Provider $service, array $orderIds, $pageToken = null)
	{
		/** @var TradingService\Marketplace\Api\Returns\Request $request */
		$request = $service->getRequestFactory()->create(TradingService\Marketplace\Api\Returns\Request::class);
		$request->setOrderIds($orderIds);
		$request->setPageToken($pageToken);

		/** @var TradingService\Marketplace\Api\Returns\Response */
		return $request->execute();
	}

	protected static function collectReturnStatuses(TradingService\Marketplace\Model\ReturnCollection $returnCollection)
	{
		$statuses = [];
		$errors = [];
		$statusMap = [
			'PICKED' => Internals\OrderReturnTable::STATUS_SUCCESS,
			'FULFILMENT_RECEIVED' => Internals\OrderReturnTable::STATUS_SUCCESS,
			'LOST' => Internals\OrderReturnTable::STATUS_FAIL,
			'CANCELLED' => Internals\OrderReturnTable::STATUS_FAIL,
			'PREPARED_FOR_UTILIZATION' => Internals\OrderReturnTable::STATUS_FAIL,
			'UTILIZED' => Internals\OrderReturnTable::STATUS_FAIL,
			'CREATED' => Internals\OrderReturnTable::STATUS_PROCESS,
			'RECEIVED' => Internals\OrderReturnTable::STATUS_PROCESS,
			'IN_TRANSIT' => Internals\OrderReturnTable::STATUS_PROCESS,
			'READY_FOR_PICKUP' => Internals\OrderReturnTable::STATUS_PROCESS,
		];

		/** @var TradingService\Marketplace\Model\Returns $return */
		foreach ($returnCollection as $return)
		{
			$shipmentStatus = $return->getShipmentStatus();

			if (!isset($statusMap[$shipmentStatus]))
			{
				$status = Internals\OrderReturnTable::STATUS_PROCESS;

				$unknownExpire = new Main\Type\DateTime();
				$unknownExpire->add('-P20D');

				if ($return->getUpdateDate()->getTimestamp() < $unknownExpire->getTimestamp())
				{
					$status = Internals\OrderReturnTable::STATUS_FAIL;
					$errors[$return->getOrderId()] = self::getMessage('ERROR_UNKNOWN_STATUS_TO_LONG');
				}
			}
			else if ($statusMap[$shipmentStatus] === Internals\OrderReturnTable::STATUS_PROCESS)
			{
				$status = Internals\OrderReturnTable::STATUS_PROCESS;

				$processExpire = new Main\Type\DateTime();
				$processExpire->add('-P60D');

				if ($return->getUpdateDate()->getTimestamp() < $processExpire->getTimestamp())
				{
					$status = Internals\OrderReturnTable::STATUS_FAIL;
					$errors[$return->getOrderId()] = self::getMessage('ERROR_PROCESS_STATUS_TO_LONG');
				}
			}
			else
			{
				$status = $statusMap[$shipmentStatus];

				if ($status === Internals\OrderReturnTable::STATUS_FAIL)
				{
					$errors[$return->getOrderId()] = self::getMessage('ERROR_STATUS_' . $shipmentStatus);
				}
			}

			$statuses[$return->getOrderId()] = $status;
		}

		return [ $statuses, $errors ];
	}

	protected static function emulateStatus(Market\Trading\Setup\Model $setup, $externalId, $accountNumber)
	{
		$logger = null;
		$audit = null;

		try
		{
			/** @var TradingService\Common\Options $options */
			$environment = $setup->getEnvironment();
			$service = $setup->wakeupService();
			$options = $service->getOptions();
			$logger = $service->getLogger();
			$server = Main\Application::getInstance()->getContext()->getServer();

			/** @var Market\Api\Model\OrderFacade $orderClassName */
			$orderClassName = $service->getModelFactory()->getOrderFacadeClassName();
			$order = $orderClassName::load($options, $externalId, $logger);

			$request = static::makeRequestFromOrder($server, $order);

			$action = $service->getRouter()->getHttpAction('order/status', $environment, $request, $server);
			$audit = $action->getAudit();

			$action->process();

			$result = true;
		}
		catch (Market\Exceptions\Api\Request $exception)
		{
			throw $exception;
		}
		catch (Main\SystemException $exception)
		{
			if ($logger === null) { throw $exception; }

			$logger->error($exception, array_filter([
				'AUDIT' => $audit,
				'ENTITY_TYPE' => Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				'ENTITY_ID' => $accountNumber,
			]));

			$result = false;
		}

		return $result;
	}

	protected static function makeRequestFromOrder(Main\Server $server, Market\Api\Model\Order $order)
	{
		return new Main\HttpRequest(
			$server,
			[], // query string
			[
				'order' => $order->getFields(),
				'emulated' => true,
				'repeat' => true,
			], // post
			[], // files
			[] // cookies
		);
	}

	protected static function markOrder(Market\Trading\Setup\Model $setup, $externalId, $accountNumber, $message)
	{
		try
		{
			$orderRegistry = $setup->getEnvironment()->getOrderRegistry();
			$orderId = $orderRegistry->search($externalId, $setup->getPlatform(), false);

			if ($orderId === null) { return; }

			$order = $orderRegistry->loadOrder($orderId);
			$order->addMarker(
				$message,
				$setup->getService()->getDictionary()->getErrorCode('RETURN_PICKUP')
			);
			$order->update();
		}
		catch (Main\SystemException $exception)
		{
			$setup->wakeupService()->getLogger()->error($exception, array_filter([
				'ENTITY_TYPE' => Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				'ENTITY_ID' => $accountNumber,
			]));
		}
	}

	protected static function commit(Market\Trading\Setup\Model $setup, $externalId, $newStatus)
	{
		Internals\OrderReturnTable::update([
			'CAMPAIGN_ID' => $setup->getId(),
			'ORDER_ID' => $externalId,
		], [
			'STATUS' => $newStatus,
			'TIMESTAMP_X' => new Market\Data\Type\CanonicalDateTime(),
		]);
	}

	protected static function getOptionPrefix()
	{
		return 'trading_order_return';
	}

	protected static function getPageSize()
	{
		$name = static::optionName('page_size');
		$option = (int)Market\Config::getOption($name, 50);

		return max(1, min(50, $option));
	}
}