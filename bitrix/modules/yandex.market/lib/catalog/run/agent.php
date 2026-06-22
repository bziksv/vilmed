<?php
namespace Yandex\Market\Catalog\Run;

use Yandex\Market\Watcher;
use Yandex\Market\Glossary;

class Agent extends Watcher\Agent\AgentFacade
{
	protected static function serviceType()
	{
		return Glossary::SERVICE_CATALOG;
	}
}