<?php
namespace Yandex\Market\Logger;

use Yandex\Market;
use Yandex\Market\Reference\Concerns;

class Level extends Market\Psr\Log\LogLevel
{
	use Concerns\HasMessage;

	protected static $variantsMap;
	protected static $variants = [
		self::EMERGENCY,
		self::ALERT,
		self::CRITICAL,
		self::ERROR,
		self::WARNING,
		self::INFO,
		self::NOTICE,
		self::DEBUG,
	];

	public static function getVariants()
	{
		return static::$variants;
	}

	public static function getTitle($level)
	{
		return self::getMessage(mb_strtoupper($level));
	}

	public static function isMatch($limit, $target)
	{
		$limitLevel = static::getVariantLevel($limit);
		$targetLevel = static::getVariantLevel($target);

		return (
			$limitLevel !== null
			&& $targetLevel !== null
			&& $limitLevel >= $targetLevel
		);
	}

	protected static function getVariantsMap()
	{
		if (static::$variantsMap === null)
		{
			static::$variantsMap = array_flip(static::$variants);
		}

		return static::$variantsMap;
	}

	protected static function getVariantLevel($level)
	{
		$map = static::getVariantsMap();

		return isset($map[$level]) ? $map[$level] : null;
	}
}