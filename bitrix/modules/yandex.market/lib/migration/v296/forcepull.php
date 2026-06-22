<?php
namespace Yandex\Market\Migration\V296;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Routine;
use Yandex\Market\Trading\Setup as TradingSetup;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings as TradingSettings;
use Yandex\Market\Ui;
use Yandex\Market\Config;

class ForcePull
{
	use Concerns\HasMessage;
	use Concerns\HasOnce;

	public function need()
	{
		return (Config::getOption('trading_yandex_mode', 'N') !== 'Y' && $this->trading() !== null);
	}

	public function schedule()
	{
		if (!$this->need()) { return; }

		$deadline = $this->deadline();

		/** @noinspection NotOptimalIfConditionsInspection */
		if (time() >= $deadline->getTimestamp() && $this->run())
		{
			$message = self::getMessage('MOVED');
		}
		else
		{
			ForcePullAgent::register([
				'next_exec' => ConvertTimeStamp($deadline->getTimestamp(), 'FULL'),
			]);

			$message = self::getMessage('SCHEDULED', [
				'#MOVE_NOW#' => Ui\Admin\Path::getModuleUrl('trading_list', [
					'lang' => LANGUAGE_ID,
					'service' => TradingService\Manager::SERVICE_MARKETPLACE,
					'postAction' => 'moveToPull',
				]),
			]);
		}

		\CAdminNotify::DeleteByTag('YAMARKET_MOVE_PUSH_295');
		\CAdminNotify::Add([
			'MODULE_ID' => 'yandex.market',
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_NORMAL,
			'MESSAGE' => $message,
			'TAG' => 'YAMARKET_FORCE_PUSH_296',
		]);
	}

	public function deadline()
	{
		$hour = '0' . mt_rand(0, 6);
		$minutes = mt_rand(10, 59);

		return new Main\Type\DateTime("2024-09-16 {$hour}:{$minutes}:00", 'Y-m-d H:i:s');
	}

	public function run()
	{
		try
		{
			if (!$this->need()) { return true; }

			$routine = new Routine\MoveToPull([]);
			$routine->run();
		}
		catch (Main\SystemException $exception)
		{
			$this->log($exception);
			return false;
		}

		return true;
	}

	public function clear()
	{
		ForcePullAgent::unregister();
		$this->clearNotify();
	}

	public function clearNotify()
	{
		\CAdminNotify::DeleteByTag('YAMARKET_FORCE_PUSH_296');
	}

	/** @return TradingSetup\Model|null */
	private function trading()
	{
		return $this->once('trading', static function() {
			$tradings = TradingSetup\Model::loadList([
				'filter' => [
					'=ACTIVE' => TradingSetup\Table::BOOLEAN_Y,
					'=TRADING_SERVICE' => TradingService\Manager::SERVICE_MARKETPLACE,
					'!=YANDEX_MODE.VALUE' => TradingService\Marketplace\Options::YANDEX_MODE_PULL,
				],
				'runtime' => [
					new Main\Entity\ReferenceField('YANDEX_MODE', TradingSettings\Table::class, [
						'=ref.NAME' => [ '?', 'YANDEX_MODE' ],
						'=this.ID' => 'ref.SETUP_ID',
					]),
				],
				'limit' => 1,
			]);

			return reset($tradings) ?: null;
		});
	}

	private function log(Main\SystemException $exception)
	{
		$trading = $this->trading();

		if ($trading === null) { return; }

		$trading->wakeupService()->getLogger()->warning($exception);
	}
}