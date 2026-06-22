<?php
namespace Yandex\Market\Catalog\Agent;

use Yandex\Market\Config;
use Yandex\Market\Data\Run\PauseException;
use Yandex\Market\Glossary;
use Yandex\Market\Logger;
use Yandex\Market\Watcher;
use Yandex\Market\Catalog;
use Yandex\Market\Api;
use Yandex\Market\Ui;
use Yandex\Market\Utils\ServerStamp;
use Yandex\Market\Reference\Concerns;

class Processor extends Watcher\Agent\Processor
{
	use Concerns\HasMessage;

	const NOTIFY_DISABLED = 'CATALOG_AGENT_DISABLED';
	const NOTIFY_NOT_ALLOWED = 'CATALOG_AGENT_NOT_ALLOWED';

	/** @var Catalog\Setup\Model|null */
	private $catalog;

	public function __construct($method, $setupId)
	{
		parent::__construct($method, Glossary::SERVICE_CATALOG, $setupId);
	}

	protected function process($action, array $parameters)
	{
		$this->catalog = Catalog\Setup\Model::loadById($this->setupId);

        ServerStamp\Facade::check();

		return (new Catalog\Run\Processor($this->catalog, $parameters))->run($action);
	}

	public function makeLogger()
	{
		$logger = new Logger\Trading\Logger();
		$logger->allowCheckExists();
		$logger->setContext('AUDIT', Logger\Trading\Audit::AGENT);
		$logger->setContext('ENTITY_TYPE', Catalog\Glossary::ENTITY_SKU);
		$logger->setContext('ENTITY_ID', 0);

		if ($this->catalog !== null)
		{
			$logger->setLevel($this->catalog->getLogLevel());
			$logger->setContext('BUSINESS_ID', $this->catalog->getBusinessId());
		}

		return $logger;
	}

	public function processException($exception)
	{
		if ($exception instanceof PauseException)
		{
			global $pPERIOD;
			$pPERIOD = $exception->getTimeout();

			return true;
		}

		if ($exception instanceof ServerStamp\ChangedException)
		{
			$this->switchOff();
			$this->notifyDisabled($exception);
		}
		else if ($exception instanceof Api\Exception\ForbiddenException)
		{
			$this->switchOff();
			$this->notifySwitchOff();
		}

		return false;
	}

	protected function switchOff()
	{
		if ($this->catalog === null) { return; }

		$this->catalog->handleRefresh(false);
		$this->catalog->handleChanges(false);
	}

	protected function notifyDisabled(ServerStamp\ChangedException $exception)
	{
		$resetUrl = Ui\Admin\Path::getModuleUrl('catalog_list', [
			'lang' => LANGUAGE_ID,
			'postAction' => 'reinstall',
		]);
		$logUrl = Ui\Admin\Path::getModuleUrl('trading_log', [
			'lang' => LANGUAGE_ID,
			'business' => $this->catalog !== null ? $this->catalog->getBusinessId() : 0,
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
			'business' => $this->catalog !== null ? $this->catalog->getBusinessId() : 0,
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