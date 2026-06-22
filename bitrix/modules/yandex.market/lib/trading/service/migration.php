<?php
namespace Yandex\Market\Trading\Service;

class Migration
{
	/** @noinspection PhpDeprecationInspection */
	private static $map = [
		Manager::SERVICE_BERU => Manager::SERVICE_MARKETPLACE,
		Manager::SERVICE_TURBO => null,
	];

	public static function getMap()
	{
		return self::$map;
	}

	public static function isDeprecated($code)
	{
		return array_key_exists($code, self::$map);
	}

	public static function getDeprecateUse($code)
	{
		return isset(self::$map[$code]) ? self::$map[$code] : null;
	}
}