<?php
namespace Yandex\Market\Trading\State;

/** @deprecated */
class SettingsSync extends Internals\AgentSkeleton
{
	const PERIOD_STEP_DEFAULT = 86400;

	public static function getDefaultParams()
	{
		return [
			'interval' => static::getPeriod('step', static::PERIOD_STEP_DEFAULT),
		];
	}

	public static function process($setupId, $errorCount = 0)
	{
		return false;
	}

	protected static function getOptionPrefix()
	{
		return 'trading_settings_sync';
	}
}