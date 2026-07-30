<?php

namespace Prime\Alerts;

class Config
{
	public const MODULE_ID = 'prime.alerts';

	public static function get(string $name, string $default = ''): string
	{
		return (string) \Bitrix\Main\Config\Option::get(self::MODULE_ID, $name, $default);
	}

	public static function isYes(string $name, string $default = 'N'): bool
	{
		return self::get($name, $default) === 'Y';
	}

	public static function isEnabled(): bool
	{
		return self::isYes('enabled', 'Y');
	}
}
