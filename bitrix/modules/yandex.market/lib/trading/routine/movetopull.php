<?php
namespace Yandex\Market\Trading\Routine;

use Bitrix\Main;
use Yandex\Market\Trading\Setup as TradingSetup;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings as TradingSettings;
use Yandex\Market\Trading\State\OrderStatusSync;
use Yandex\Market\Migration;
use Yandex\Market\Config;
use Yandex\Market\Utils;
use Yandex\Market\Reference\Agent;

class MoveToPull
{
	protected $filter = [];
	protected $warehouses = [];

	public function __construct(array $filter)
	{
		$this->filter = $filter;
	}

	public function run()
	{
		$this->prepareEnvironment();

		$tradingSetups = TradingSetup\Model::loadList($this->filter);

		foreach ($tradingSetups as $tradingSetup)
		{
			$this->process($tradingSetup);
		}

		$this->resetEnvironment();
	}

	protected function prepareEnvironment()
	{
		Utils\HttpConfiguration::stamp();
		Utils\HttpConfiguration::setGlobalTimeout(5);
	}

	protected function resetEnvironment()
	{
		Utils\HttpConfiguration::restore();
	}

	protected function process(TradingSetup\Model $setup)
	{
		try
		{
			if (!$setup->isActive()) { return; }

			$service = $setup->wakeupService();

			if (!($service instanceof TradingService\Marketplace\Provider)) { return; }

			$options = $service->getOptions();

			if ($options->getYandexMode() === TradingService\Marketplace\Options::YANDEX_MODE_PULL) { return; }

			$this->pushMode($service);
			$this->saveOptions($setup);
			$this->scheduleAgent($setup);
		}
		/** @noinspection PhpRedundantCatchClauseInspection */
		catch (Main\SystemException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);
		}
	}

	protected function pushMode(TradingService\Marketplace\Provider $service)
	{
		$options = $service->getContainer()->get(TradingService\Common\Command\PushApiMode::class);
		$options->run(TradingService\Marketplace\Options::YANDEX_MODE_PULL);
	}

	protected function saveOptions(TradingSetup\Model $setup)
	{
		$row = [
			'SETUP_ID' => $setup->getId(),
			'NAME' => 'YANDEX_MODE',
			'VALUE' => TradingService\Marketplace\Options::YANDEX_MODE_PULL,
		];

		TradingSettings\Table::addBatch([ $row ], true);
	}

	protected function scheduleAgent(TradingSetup\Model $setup)
	{
		$interval = (int)Config::getOption('trading_pull_period', 600);

		$nextExec = new Main\Type\DateTime();
		$nextExec->add(sprintf('PT%sS', $interval));

		OrderStatusSync::register([
			'method' => 'start',
			'interval' => $interval,
			'next_exec' => ConvertTimeStamp($nextExec->getTimestamp(), 'FULL'),
			'arguments' => [ (string)$setup->getId() ],
			'update' => Agent\Controller::UPDATE_RULE_STRICT,
		]);
	}

    public function clearUpdater()
    {
        \CAdminNotify::DeleteByTag('YAMARKET_MOVE_PUSH_295');

		$migration = new Migration\V296\ForcePull();
	    $migration->clear();
    }
}