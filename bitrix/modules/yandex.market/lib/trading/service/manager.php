<?php
namespace Yandex\Market\Trading\Service;

use Yandex\Market;
use Bitrix\Main;

class Manager
{
	const SERVICE_MARKETPLACE = 'marketplace';
	/** @deprecated */
	const SERVICE_TURBO = 'turbo';
	/** @deprecated */
	const SERVICE_BERU = 'beru';

	const BEHAVIOR_DEFAULT = 'default';
	const BEHAVIOR_FBS = self::BEHAVIOR_DEFAULT;
	const BEHAVIOR_DBS = 'dbs';
	const BEHAVIOR_BUSINESS = 'business';

	protected static $userServices;

	/** @return Reference\Provider */
	public static function createProvider($code, $behavior = Manager::BEHAVIOR_DEFAULT)
	{
		$variant = static::makeVariant($code, $behavior);
		$classMap = static::getUserServices() + static::getSystemVariants();

		if (!isset($classMap[$variant]))
		{
			throw new Main\NotImplementedException('service provider not implemented for ' . $code);
		}

		$className = $classMap[$variant];

		Market\Reference\Assert::isSubclassOf($className, Reference\Provider::class);

		return new $className();
	}

	public static function getServices()
	{
		$serviceMap = [];

		foreach (static::getVariants() as $variant)
		{
			$variantService = static::extractServiceCode($variant);

			$serviceMap[$variantService] = true;
		}

		return array_keys($serviceMap);
	}

	public static function getBehaviors($serviceCode)
	{
		$behaviorMap = [];

		foreach (static::getVariants() as $variant)
		{
			$variantService = static::extractServiceCode($variant);

			if ($variantService === $serviceCode)
			{
				$behaviorCode = static::extractBehaviorCode($variant);
				$behaviorMap[$behaviorCode] = true;
			}
		}

		return array_keys($behaviorMap);
	}

	public static function isExists($code, $behavior = Manager::BEHAVIOR_DEFAULT)
	{
		$variant = static::makeVariant($code, $behavior);
		$variants = static::getVariants();

		return in_array($variant, $variants, true);
	}

	public static function getVariants()
	{
		return array_keys(static::getSystemVariants() + static::getUserServices());
	}

	/** @noinspection PhpDeprecationInspection */
	protected static function getSystemVariants()
	{
		return [
			static::SERVICE_MARKETPLACE . ':' . static::BEHAVIOR_BUSINESS => MarketplaceBusiness\Provider::class,
			static::SERVICE_MARKETPLACE . ':' . static::BEHAVIOR_DBS => MarketplaceDbs\Provider::class,
			static::SERVICE_MARKETPLACE => Marketplace\Provider::class,
			static::SERVICE_BERU => Beru\Provider::class,
			static::SERVICE_TURBO => Turbo\Provider::class,
		];
	}

	protected static function getUserServices()
	{
		if (static::$userServices === null)
		{
			static::$userServices = static::loadUserServices();
		}

		return static::$userServices;
	}

	protected static function loadUserServices()
	{
		$result = [];
		$moduleName = Market\Config::getModuleName();
		$eventName = 'onTradingServiceBuildList';

		$event = new Main\Event($moduleName, $eventName);
		$event->send();

		foreach ($event->getResults() as $eventResult)
		{
			if ($eventResult->getType() !== Main\EventResult::SUCCESS) { continue; }

			$eventData = $eventResult->getParameters();

			if (!isset($eventData['SERVICE']))
			{
				throw new Main\ArgumentException('SERVICE must be defined for event result ' . $eventName);
			}

			$serviceKey = isset($eventData['BEHAVIOR'])
				? static::makeVariant($eventData['SERVICE'], $eventData['BEHAVIOR'])
				: static::makeVariant($eventData['SERVICE']);

			if (!isset($eventData['PROVIDER']))
			{
				throw new Main\ArgumentException('PROVIDER must be defined for service ' . $serviceKey);
			}

			if (!is_subclass_of($eventData['PROVIDER'], Reference\Provider::class))
			{
				throw new Main\ArgumentException($eventData['PROVIDER'] . ' must extends ' . Reference\Provider::class . ' for service ' . $serviceKey);
			}

			$result[$serviceKey] = $eventData['PROVIDER'];
		}

		return $result;
	}

	protected static function makeVariant($code, $behavior = Manager::BEHAVIOR_DEFAULT)
	{
		$result = $code;
		$behavior = (string)$behavior;

		if ($behavior !== '' && $behavior !== static::BEHAVIOR_DEFAULT)
		{
			$result .= ':' . $behavior;
		}

		return $result;
	}

	protected static function extractServiceCode($code)
	{
		list($service) = explode(':', $code, 2);

		return $service;
	}

	protected static function extractBehaviorCode($code)
	{
		$parts = explode(':', $code, 2);

		if (count($parts) === 2)
		{
			return $parts[1];
		}

		return static::BEHAVIOR_DEFAULT;
	}
}