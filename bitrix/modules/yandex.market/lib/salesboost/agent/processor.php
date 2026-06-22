<?php
namespace Yandex\Market\SalesBoost\Agent;

use Yandex\Market\Api;
use Yandex\Market\Config;
use Yandex\Market\Glossary;
use Yandex\Market\Logger;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Watcher;
use Yandex\Market\SalesBoost\Setup;
use Yandex\Market\SalesBoost\Run;
use Yandex\Market\Utils\ServerStamp;
use Yandex\Market\Ui;

class Processor extends Watcher\Agent\Processor
{
	use Concerns\HasOnce;
	use Concerns\HasMessage;

	const NOTIFY_DISABLED = 'BOOST_AGENT_DISABLED';
	const NOTIFY_NOT_ALLOWED = 'BOOST_AGENT_NOT_ALLOWED';

	/** @var Setup\Model|null */
	private $boost;

	public function __construct($method, $setupId)
	{
		parent::__construct($method, Glossary::SERVICE_SALES_BOOST, $setupId);
	}

	protected function process($action, array $parameters)
	{
		global $pPERIOD;

		$pPERIOD = 5;
		$this->boost = Setup\Model::loadById($this->setupId);

		ServerStamp\Facade::check();

		$processor = new Run\Processor($parameters + [
			'boosts' => [ $this->boost->getId() ],
		]);

		return $processor->run($action);
	}

	public function makeLogger()
	{
		$logger = new Logger\Trading\Logger(Glossary::SERVICE_SALES_BOOST, $this->setupId);
		$logger->setContext('AUDIT', Logger\Trading\Audit::SALES_BOOST);

		if ($this->boost !== null)
		{
			$logger->setContext('BUSINESS_ID', $this->boost->getBusinessId());
		}

		return $logger;
	}

	public function processException($exception)
	{
		if ($exception instanceof ServerStamp\ChangedException)
		{
			$this->switchOff();
			$this->notifyDisabled($exception);

			return false;
		}

		if (
			$exception instanceof Api\Exception\LockedException
			|| $exception instanceof Api\Exception\ForbiddenException
		)
		{
			$this->switchOff();
			$this->notifySwitchOff();

			return false;
		}

		return parent::processException($exception);
	}

	protected function switchOff()
	{
		if ($this->boost === null) { return; }

		$this->boost->handleRefresh(false);
		$this->boost->handleChanges(false);
	}

	protected function notifyDisabled(ServerStamp\ChangedException $exception)
	{
		$resetUrl = Ui\Admin\Path::getModuleUrl('sales_boost_list', [
			'lang' => LANGUAGE_ID,
			'postAction' => 'reinstall',
		]);
		$logUrl = Ui\Admin\Path::getModuleUrl('trading_log', [
			'lang' => LANGUAGE_ID,
			'business' => $this->boost !== null ? $this->boost->getBusinessId() : 0,
			'find_level' => Logger\Level::ERROR,
			'set_filter' => 'Y',
			'apply_filter' => 'Y',
		]);

		\CAdminNotify::Add([
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_ERROR,
			'MODULE_ID' => Config::getModuleName(),
			'TAG' => static::NOTIFY_DISABLED,
			'MESSAGE' => self::getMessage('DISABLED', [
				'#MESSAGE#' => $exception->getMessage(),
				'#RESET_URL#' => $resetUrl,
				'#LOG_URL#' => $logUrl,
			], $exception->getMessage()),
		]);
	}

	protected function notifySwitchOff()
	{
		$logUrl = Ui\Admin\Path::getModuleUrl('trading_log', [
			'lang' => LANGUAGE_ID,
			'business' => $this->boost !== null ? $this->boost->getBusinessId() : 0,
			'find_level' => Logger\Level::ERROR,
			'set_filter' => 'Y',
			'apply_filter' => 'Y',
		]);

		\CAdminNotify::Add([
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_ERROR,
			'MODULE_ID' => Config::getModuleName(),
			'TAG' => static::NOTIFY_NOT_ALLOWED,
			'MESSAGE' => self::getMessage('SWITCH_OFF', [
				'#LOG_URL#' => $logUrl,
			]),
		]);
	}
}