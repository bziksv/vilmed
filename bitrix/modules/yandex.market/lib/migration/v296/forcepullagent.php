<?php
namespace Yandex\Market\Migration\V296;

use Yandex\Market\Reference\Agent;

class ForcePullAgent extends Agent\Base
{
	public static function run($repeat = 0)
	{
		$migration = new ForcePull();
		$moved = $migration->run();

		if (!$moved) { return ++$repeat < 5 ? [ $repeat ] : false; }

		$migration->clearNotify();

		return false;
	}
}