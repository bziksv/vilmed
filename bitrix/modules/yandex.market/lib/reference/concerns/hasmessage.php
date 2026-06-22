<?php

namespace Yandex\Market\Reference\Concerns;

use Yandex\Market\Config;
use Yandex\Market\Utils\MessageRegistry;

trait HasMessage
{
	private static function includeSelfMessages()
	{
		MessageRegistry::getModuleInstance()->load(self::class);
	}

	protected static function getMessagePrefix()
	{
		return MessageRegistry::getModuleInstance()->getPrefix(self::class) . '_';
	}

	protected static function getMessage($code, $replaces = null, $fallback = null)
	{
		$messageRegistry = MessageRegistry::getModuleInstance();
		$className = self::class;

		$messageRegistry->load($className);

		$prefixes = [
			$messageRegistry->getPrefix($className),
			$messageRegistry->getCompatiblePrefix($className),
		];

		foreach ($prefixes as $prefix)
		{
			$message = Config::getLang($prefix . '_' . $code, $replaces, '');

			if ($message !== '') { return $message; }
		}

		return ($fallback !== null ? $fallback : $code);
	}
}