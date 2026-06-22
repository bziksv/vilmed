<?php
namespace Yandex\Market\Trading\State;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class OrderStatusSync extends Internals\AgentSkeleton
{
	use Market\Reference\Concerns\HasMessage;

	const NOTIFY_FORBIDDEN = 'ORDER_PULL_DISABLED';

	protected static $expireDate;

	public static function getDefaultParams()
	{
		return [
			'interval' => static::getPeriod('restart', 86400),
            'sort' => 400, // more priority
		];
	}

	public static function start($campaignId)
	{
		try
		{
			global $pPERIOD;

			$setup = static::getCampaignSetup($campaignId);
			$options = $setup->wakeupService()->getOptions();

			$repeatSync = static::sync($campaignId);

			if ($repeatSync !== false)
			{
				static::register([
					'method' => 'sync',
					'arguments' => is_array($repeatSync) ? $repeatSync : [ $campaignId ],
					'interval' => static::getPeriod('step', static::PERIOD_STEP_DEFAULT),
				]);
			}

			if (
				$options instanceof TradingService\Marketplace\Options
				&& $options->getYandexMode() === TradingService\Marketplace\Options::YANDEX_MODE_PULL
			)
			{
				$pPERIOD = (int)Market\Config::getOption('trading_pull_period', 600);
			}

			return true;
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			return false;
		}
		catch (Main\ObjectPropertyException $exception)
		{
			return false;
		}
	}

	protected static function canRepeat($exception, $errorCount)
	{
		if (static::isRequestForbidden($exception))
		{
			return $errorCount < 1; // only first error skipped
		}

		return parent::canRepeat($exception, $errorCount);
	}

	protected static function isRequestForbidden($exception)
	{
		return (
            $exception instanceof Market\Exceptions\Api\Request
            && in_array($exception->getErrorCode(), ['FORBIDDEN', 'UNAUTHORIZED'], true)
        );
	}

	protected static function logError(Market\Trading\Setup\Model $setup, $message, $arguments = null)
	{
		parent::logError($setup, $message, $arguments);

		if (static::isRequestForbidden($message))
		{
			$switchOffArguments = ($arguments !== null ? array_slice($arguments, 0, 1) : [ $setup->getId() ]);

			static::switchOff($switchOffArguments);
			static::notifySwitchOffMethod($setup, 'FORBIDDEN');
		}
	}

	protected static function notifySwitchOffMethod(Market\Trading\Setup\Model $setup, $reason)
	{
		$tag = static::NOTIFY_FORBIDDEN . '_' . $setup->getId();
		$setupUrl = Market\Ui\Admin\Path::getModuleUrl('trading_edit', [
			'lang' => LANGUAGE_ID,
			'business' => $setup->getBusinessId(),
			'id' => $setup->getId(),
		]);
		$logUrl = Market\Ui\Admin\Path::getModuleUrl('trading_log', [
			'lang' => LANGUAGE_ID,
			'business' => $setup->getBusinessId(),
			'find_level' => Market\Logger\Level::ERROR,
			'find_setup' => $setup->getId(),
			'set_filter' => 'Y',
			'apply_filter' => 'Y',
		]);

		\CAdminNotify::Add([
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_ERROR,
			'MODULE_ID' => Market\Config::getModuleName(),
			'TAG' => $tag,
			'MESSAGE' => self::getMessage($reason, [
				'#SETUP_URL#' => $setupUrl,
				'#LOG_URL#' => $logUrl,
			]),
		]);
	}

	protected static function switchOff(array $arguments = null)
	{
		$methods = [
			'start',
			'sync',
			'fork',
		];

		foreach ($methods as $method)
		{
			static::unregister([
				'method' => $method,
				'arguments' => $arguments,
				'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
			]);
		}
	}

	public static function sync($campaignId, $offset = null, $errorCount = 0)
	{
		return static::wrapAction(
			[static::class, 'syncBody'],
			[ $campaignId, $offset ],
			$errorCount
		);
	}

	protected static function syncBody($campaignId, $offset = null)
	{
		$setup = static::getCampaignSetup($campaignId);
		$campaignId = static::castCampaignId($setup, $campaignId);
		$justStarted = ($offset === null);
		$offset = static::sanitizeOffset($campaignId, $offset);
		$dates = static::getSyncDates($offset['start'], $offset['finish']);
		$filter = static::makeDateFilter($dates, $offset['date']);

		if ($filter === null) { return false; }

		$service = $setup->wakeupService();
		$orderCollection = static::loadOrderCollection($service, $filter, $offset['page']);
		$pager = $orderCollection->getPager();
		$hasNext = false;
		$orders = static::mapOrderCollection($orderCollection);
		$accountNumberMap = static::getAccountNumberMap($orders, $setup);
		$first = true;
		$newUpdatedAt = $justStarted ? static::defaultUpdatedAt($offset['start'], $offset['finish']) : null;

		if (isset($offset['order']) && !isset($orders[$offset['order']]))
		{
			unset($offset['order']);
		}

		foreach ($orders as $orderId => $order)
		{
			if (isset($offset['order']))
			{
				if ($offset['order'] !== $orderId) { continue; }

				unset($offset['order']);
			}

			if ($justStarted)
			{
				if ($order instanceof TradingService\Marketplace\Model\Order)
				{
					$orderUpdatedAt = $order->getUpdatedAt();

					if ($newUpdatedAt === null || Market\Data\DateTime::compare($newUpdatedAt, $orderUpdatedAt) === -1)
					{
						$newUpdatedAt = $orderUpdatedAt;
						$newUpdatedAt->add('PT1S');
					}
				}
				else if ($newUpdatedAt === null)
				{
					$newUpdatedAt = clone $offset['start'];
					$newUpdatedAt->add('-PT1S');
				}
			}

			if (!$first && static::isTimeExpired())
			{
				$hasNext = true;
				$offset['order'] = $orderId;
				break;
			}

			$first = false;

			if (!isset($accountNumberMap[$orderId]))
			{
				if (!static::canAccept($setup)) { continue; }
				if (static::isExpired($order) || !static::isNeedOrder($setup, $order)) { continue; }

                $inAcceptInterval = static::isAcceptInterval($order, $offset['finish']);

				if (static::isWaitOrder($order))
				{
                    if ($inAcceptInterval) { continue; }

					static::createFork($campaignId, $orderId, true);
					continue;
				}

                if (!$inAcceptInterval) { continue; }

				$accepted = static::emulateAccept($setup, $order);

				if ($accepted === false)
				{
					static::createFork($campaignId, $orderId);
					continue;
				}

				if ($accepted === null) { continue; }

				$accountNumberMap[$orderId] = $accepted;
			}

			$updated = static::emulateStatus($setup, $order, $accountNumberMap[$orderId]);

			if ($updated === false)
			{
				static::createFork($campaignId, $orderId);
			}
		}

		if ($newUpdatedAt !== null)
		{
			static::commitUpdatedAt($campaignId, $newUpdatedAt);
		}

		if (!$hasNext && $pager !== null && $pager->hasNext())
		{
			$hasNext = true;

			++$offset['page'];
			$offset = array_diff_key($offset, [ 'order' => true ]);
		}

		if (!$hasNext && count($dates) > $offset['date'] + 1)
		{
			$hasNext = true;

			++$offset['date'];
			$offset['page'] = 1;
			$offset = array_diff_key($offset, [ 'order' => true ]);
		}

		return $hasNext ? [ $campaignId, static::packOffset($offset) ] : false;
	}

	protected static function castCampaignId(Market\Trading\Setup\Model $trading, $campaignId)
	{
		if ($trading->getId() !== (int)$campaignId)
		{
			return $campaignId;
		}

		$tradingCampaignId = $trading->getCampaignId();

		if ($tradingCampaignId > 0)
		{
			return $tradingCampaignId;
		}

		return $campaignId;
	}

	protected static function sanitizeOffset($campaignId, $offset = null)
	{
		$defaults = [
			'start' => new Main\Type\DateTime(),
			'finish' => static::getUpdatedAt($campaignId),
			'date' => 0,
			'page' => 1,
		];

		if ($defaults['finish'] === null)
		{
			$finish = new Main\Type\DateTime();
			$finish->add(sprintf('-P%sD', static::getSyncDaysStep() - 1));

			$defaults['finish'] = $finish;
		}

		if (is_array($offset))
		{
			$offset = static::compatibleOffset($offset);
			$result = static::unpackOffset($offset) + $defaults;
		}
		else if ($offset !== null)
		{
			$result = $defaults;
			$result['page'] = $offset;
		}
		else
		{
			$result = $defaults;
		}

		return $result;
	}

	protected static function compatibleOffset(array $offset)
	{
		if (!isset($offset['start']) || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $offset['start'])) { return $offset; }

		$start = new Main\Type\DateTime($offset['start'], 'Y-m-d');
		$finish = new Main\Type\DateTime();
		$finish->add(sprintf('-P%sD', static::getSyncDaysStep() - 1));

		$offset['start'] = $start->format(\DateTime::ATOM);
		$offset['finish'] = $finish->format(\DateTime::ATOM);

		return $offset;
	}

	protected static function unpackOffset(array $offset)
	{
		$offset['start'] = new Main\Type\DateTime($offset['start'], \DateTime::ATOM);
		$offset['finish'] = new Main\Type\DateTime($offset['finish'], \DateTime::ATOM);

		return $offset;
	}

	protected static function packOffset(array $offset)
	{
		$offset['start'] = $offset['start']->format(\DateTime::ATOM);
		$offset['finish'] = $offset['finish']->format(\DateTime::ATOM);

		return $offset;
	}

	public static function createFork($campaignId, $orderId, $force = false)
	{
		if (!$force && !static::canFork()) { return; }

		$interval = static::getPeriod('timeout', static::PERIOD_TIMEOUT_DEFAULT);
		$nextExec = new Main\Type\DateTime();
		$nextExec->add(sprintf('PT%sS', $interval));

		static::register([
			'method' => 'fork',
			'arguments' => [ $campaignId, $orderId ],
			'interval' => $interval,
			'next_exec' => ConvertTimeStamp($nextExec->getTimestamp(), 'FULL'),
		]);
	}

	/** @noinspection PhpUnused */
	public static function fork($campaignId, $orderId, $repeat = 0, $errorCount = 0)
	{
		return static::wrapAction(
			[static::class, 'forkBody'],
			[ $campaignId, $orderId, $repeat ],
			$errorCount
		);
	}

	protected static function forkBody($campaignId, $orderId, $repeat = 0)
	{
		$setup = static::getCampaignSetup($campaignId);
		$service = $setup->wakeupService();
		$order = static::loadOrder($service, $orderId);

		if (static::isExpired($order) || !static::isNeedOrder($setup, $order)) { return false; }
		if (static::isWaitOrder($order)) { return true; }

		$accepted = static::emulateAccept($setup, $order);

		if ($accepted === null) { return false; }

		if ($accepted !== false)
		{
			$updated = static::emulateStatus($setup, $order, $accepted);

			if ($updated) { return false; }
		}

		++$repeat;

		return $repeat < static::getForkLimit() ? [ $campaignId, $orderId, $repeat ] : false;
	}

	protected static function canFork()
	{
		return static::getForkLimit() > 0;
	}

	protected static function getForkLimit()
	{
		$name = static::optionName('fork_limit');
		$option = (int)Market\Config::getOption($name, 3);

		return max(0, $option);
	}

	protected static function getSyncDates(Main\Type\DateTime $startDate, Main\Type\DateTime $finishDate)
	{
		$days = static::getSyncDaysLimit();
		$step = static::getSyncDaysStep();
		$count = ceil($days / $step);
		$result = [];

		$loopDate = clone $startDate;
		$loopDate->add('P1D'); // fix query over date limit with local timezone

		for ($i = 1; $i <= $count; $i++)
		{
			$loopDate->add(sprintf('-P%sD', $step));

			if ($finishDate->getTimestamp() >= $loopDate->getTimestamp())
			{
				$result[] = clone $finishDate;
				break;
			}

			$result[] = clone $loopDate;
		}

		return $result;
	}

	protected static function getSyncDaysStep()
	{
		$name = static::optionName('days_step');
		$option = (int)Market\Config::getOption($name, 30);

		return max(1, $option);
	}

	protected static function getSyncDaysLimit()
	{
		$name = static::optionName('days_limit');
		$option = (int)Market\Config::getOption($name, 60);

		return max(1, $option);
	}

	protected static function getOptionPrefix()
	{
		return 'trading_status_sync';
	}

	protected static function getPageSize()
	{
		$name = static::optionName('page_size');
		$option = (int)Market\Config::getOption($name, 50);

		return max(1, min(50, $option));
	}

	protected static function makeDateFilter(array $dates, $offset = 0)
	{
		if (!isset($dates[$offset])) { return null; }

		$result = [
			'updatedAtFrom' => $dates[$offset],
		];

		if ($offset > 0)
		{
			$result['updatedAtTo'] = $dates[$offset - 1];
		}

		return $result;
	}

    protected static function defaultUpdatedAt(Main\Type\DateTime $start, Main\Type\DateTime $finish)
    {
        $default = clone $start;
        $default->add('-P1D');

        if (Market\Data\DateTime::compare($default, $finish) !== 1) { return null; }

        return $default;
    }

	protected static function commitUpdatedAt($campaignId, Main\Type\DateTime $newUpdatedAt)
	{
		$lastUpdatedAt = static::getUpdatedAt($campaignId);

		if ($lastUpdatedAt !== null && Market\Data\DateTime::compare($newUpdatedAt, $lastUpdatedAt) !== 1) { return; }

		$name = static::optionName('updated_' . $campaignId);

		Market\State::set($name, $newUpdatedAt->format(\DateTime::ATOM));
	}

	protected static function getUpdatedAt($campaignId)
	{
		$name = static::optionName('updated_' . $campaignId);
		$stored = (string)Market\State::get($name);

		if ($stored === '') { return null; }

		return new Main\Type\DateTime($stored, \DateTime::ATOM);
	}

	protected static function loadOrderCollection(TradingService\Reference\Provider $service, array $filter = [], $page = 0)
	{
		/** @var TradingService\Common\Options $options */
		$options = $service->getOptions();
		$pageSize = static::getPageSize();
		$parameters = [
			'page' => max($page, 1),
			'pageSize' => $pageSize,
		];
		$parameters += $filter;

        if (Market\Config::isDevMode())
        {
            $parameters['fake'] = true;
        }

		$orderFacade = $service->getModelFactory()->getOrderFacadeClassName();

		return $orderFacade::loadList($options, $parameters);
	}

	protected static function loadOrder(TradingService\Reference\Provider $service, $orderId)
	{
		/** @var TradingService\Common\Options $options */
		$options = $service->getOptions();
		$orderFacade = $service->getModelFactory()->getOrderFacadeClassName();

		return $orderFacade::load($options, $orderId);
	}

	protected static function mapOrderCollection(Market\Api\Model\OrderCollection $orderCollection)
	{
		$result = [];

		/** @var Market\Api\Model\Order $order */
		foreach ($orderCollection as $order)
		{
			$result[$order->getId()] = $order;
		}

		return $result;
	}

	protected static function getAccountNumberMap(array $orders, Market\Trading\Setup\Model $setup)
	{
		return $setup->getEnvironment()->getOrderRegistry()->searchList(
			array_keys($orders),
			$setup->getPlatform(),
			false
		);
	}

	protected static function canAccept(Market\Trading\Setup\Model $setup)
	{
		$options = $setup->wakeupService()->getOptions();

		return (
			$options instanceof TradingService\Marketplace\Options
			&& $options->getYandexMode() === TradingService\Marketplace\Options::YANDEX_MODE_PULL
		);
	}

	protected static function isExpired(Market\Api\Model\Order $order)
	{
		$expireDate = static::getExpireDate();
		$createDate = $order->getCreationDate();

		return Market\Data\DateTime::compare($createDate, $expireDate) === -1;
	}

	protected static function getExpireDate()
	{
		if (static::$expireDate === null)
		{
			$expireDays = static::getExpireDays();
			$expireDate = new Main\Type\DateTime();
			$expireDate->add(sprintf('-P%sD', $expireDays));

			static::$expireDate = $expireDate;
		}

		return static::$expireDate;
	}

	protected static function getExpireDays()
	{
		return Internals\DataCleaner::getExpireDays('status');
	}

    protected static function isAcceptInterval(Market\Api\Model\Order $order, Main\Type\DateTime $finish)
    {
        $intervalName = static::optionName('accept_interval');
        $interval = max(1, (int)Market\Config::getOption($intervalName, 6));

        $expire = clone $finish;
        $expire->add(sprintf('-PT%sH', $interval));

        return Market\Data\DateTime::compare($order->getCreationDate(), $expire) !== -1;
    }

	protected static function isNeedOrder(Market\Trading\Setup\Model $setup, Market\Api\Model\Order $order)
	{
		$serviceStatus = $setup->getService()->getStatus();

		if ($serviceStatus->isCanceled($order->getStatus(), $order->getSubStatus()))
		{
			return false;
		}

		if (!($serviceStatus instanceof TradingService\Marketplace\Status)) { return true; }

		if ($serviceStatus->isOrderDelivered($order->getStatus()))
		{
			$expireDate = new Main\Type\DateTime();
			$expireDate->add('-P7D');

			return Market\Data\DateTime::compare($order->getCreationDate(), $expireDate) !== -1;
		}

		return true;
	}

	protected static function isWaitOrder(Market\Api\Model\Order $order)
	{
		if (!($order instanceof TradingService\Marketplace\Model\Order)) { return false; }
		if (!in_array($order->getStatus(), [
			TradingService\Marketplace\Status::STATUS_UNPAID,
			TradingService\Marketplace\Status::STATUS_PLACING,
			TradingService\Marketplace\Status::STATUS_RESERVED,
		], true)) { return false; }

		$buyer = $order->getBuyer();

		return (
			$buyer === null
			|| $buyer->getType() !== TradingService\Marketplace\Model\Order\Buyer::TYPE_BUSINESS
		);
	}

	protected static function emulateAccept(Market\Trading\Setup\Model $setup, Market\Api\Model\Order $order)
	{
		$response = static::emulateAction($setup, $order, 'order/accept');

		if (!isset($response['order'])) { return false; }

		return isset($response['order']['id']) ? $response['order']['id'] : null;
	}

	protected static function emulateStatus(Market\Trading\Setup\Model $setup, Market\Api\Model\Order $order, $accountNumber)
	{
		$response = static::emulateAction($setup, $order, 'order/status', $accountNumber);

		return $response !== null;
	}

	protected static function emulateAction(Market\Trading\Setup\Model $setup, Market\Api\Model\Order $order, $path, $accountNumber = null)
	{
		$logger = null;
		$audit = null;

		try
		{
			$environment = $setup->getEnvironment();
			$service = $setup->wakeupService();
			$logger = $service->getLogger();
			$server = Main\Application::getInstance()->getContext()->getServer();
			$request = static::makeRequestFromOrder($server, $order);

			$action = $service->getRouter()->getHttpAction($path, $environment, $request, $server);
			$audit = $action->getAudit();

			if ($logger instanceof Market\Logger\Trading\Logger)
			{
				$logger->setContext('AUDIT', $audit);
			}

			$action->process();

			$result = $action->getResponse()->getRaw();
		}
		catch (Main\SystemException $exception)
		{
			if ($logger === null) { throw $exception; }

			$logger->error($exception, array_filter([
				'AUDIT' => $audit,
				'ENTITY_TYPE' => Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				'ENTITY_ID' => $accountNumber,
			]));

			$result = null;
		}
		/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
		catch (\Throwable $exception)
		{
			if ($logger === null) { throw $exception; }

			$logger->error($exception, array_filter([
				'AUDIT' => $audit,
				'ENTITY_TYPE' => Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
				'ENTITY_ID' => $accountNumber,
			]));

			throw $exception;
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
				'download' => true,
			], // post
			[], // files
			[] // cookies
		);
	}
}