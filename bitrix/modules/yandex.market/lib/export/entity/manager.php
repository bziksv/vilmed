<?php

namespace Yandex\Market\Export\Entity;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Reference\Assert;

class Manager
{
	const TYPE_IBLOCK_ELEMENT_FIELD = 'iblock_element_field';
	const TYPE_IBLOCK_ELEMENT_PROPERTY = 'iblock_element_property';
	const TYPE_IBLOCK_ELEMENT_SEO = 'iblock_element_seo';
	const TYPE_IBLOCK_OFFER_FIELD = 'iblock_offer_field';
	const TYPE_IBLOCK_OFFER_PROPERTY = 'iblock_offer_property';
	const TYPE_IBLOCK_OFFER_SEO = 'iblock_offer_seo';
	const TYPE_IBLOCK_PROPERTY_FEATURE = 'iblock_property_feature';
	const TYPE_IBLOCK_SECTION = 'iblock_section';
	const TYPE_MARKET_PROPERTY = 'market_property';
	const TYPE_CATALOG_PRICE = 'catalog_price';
	const TYPE_CATALOG_PRICE_MATRIX = 'catalog_priceMatrix';
	const TYPE_CATALOG_PRODUCT = 'catalog_product';
	const TYPE_CATALOG_STORE = 'catalog_store';
	const TYPE_PROMO_PRICE = 'promo_price';
	const TYPE_CURRENCY = 'currency';
	const TYPE_TEXT = 'text';
	const TYPE_TEMPLATE = 'template';
	const TYPE_FORMULA = 'formula';

	const TYPE_TRADING_RESERVE = 'trading_reserve';

	const CONTROL_SELECT = 'select';
	const CONTROL_TEXT = 'text';
	const CONTROL_TEMPLATE = 'template';
	const CONTROL_FORMULA = 'formula';

	protected static $entityCache = [];
	protected static $customEntityList;

	/** @return Reference\Source */
	public static function getSource($type)
	{
		return static::getTypeInstance($type, 'Source');
	}

	public static function getSourceTypeList()
	{
		$result = static::getDefaultTypes();
		$customTypes = static::getCustomEntityList();

		foreach ($customTypes as $customType => $customData)
		{
			$result[] = $customType;
		}

		return $result;
	}

	/** @return Reference\Event */
	public static function getEvent($type)
	{
		return static::getTypeInstance($type, 'Event', Reference\Event::class);
	}

	protected static function getTypeInstance($type, $part, $fallbackClass = null)
	{
		$cacheKey = $type . ':' . $part;

		if (isset(static::$entityCache[$cacheKey]))
		{
			return static::$entityCache[$cacheKey];
		}

		$className = static::getTypeClassName($type, $part, $fallbackClass);

		if ($className === null)
		{
			throw new Main\ObjectNotFoundException("{$part} not found for {$type}");
		}

		$result = new $className;
		$result->setType($type);

		static::$entityCache[$cacheKey] = $result;

		return $result;
	}

	protected static function getTypeClassName($type, $part, $fallbackClass = null)
	{
		$customEntityList = static::getCustomEntityList();

		if (isset($customEntityList[$type]))
		{
			return $customEntityList[$type][$part] ?: $fallbackClass;
		}

		$namespace = static::getTypeNamespace($type);
		$result = $namespace . '\\' . $part;

		if (class_exists($result)) { return $result; }

		return $fallbackClass;
	}

	protected static function getTypeNamespace($type)
	{
		$parts = explode('_', $type);

		return __NAMESPACE__ . '\\' . implode('\\', $parts);
	}

	protected static function getCustomEntityList()
	{
		if (!isset(static::$customEntityList))
		{
			static::$customEntityList = static::loadCustomEntityList();
		}

		return static::$customEntityList;
	}

	protected static function loadCustomEntityList()
	{
		$result = [];

		$event = new Main\Event(Market\Config::getModuleName(), 'onExportEntityTypeBuildList');
		$event->send();

		foreach ($event->getResults() as $eventResult)
		{
			$eventData = $eventResult->getParameters();

			if (!isset($eventData['TYPE'])) { continue; }

			Assert::notNull($eventData['SOURCE_CLASS_NAME'], 'eventData[SOURCE_CLASS_NAME]');
			Assert::isSubclassOf($eventData['SOURCE_CLASS_NAME'], Reference\Source::class);

			$eventClassName = null;

			if (isset($eventData['EVENT_CLASS_NAME']))
			{
				Assert::isSubclassOf($eventData['EVENT_CLASS_NAME'], Reference\Event::class);

				$eventClassName = $eventData['EVENT_CLASS_NAME'];
			}

			$result[$eventData['TYPE']] = [
				'Source' => $eventData['SOURCE_CLASS_NAME'],
				'Event' => $eventClassName,
			];
		}

		return $result;
	}

	protected static function getDefaultTypes()
	{
		return [
			static::TYPE_IBLOCK_ELEMENT_FIELD,
			static::TYPE_IBLOCK_ELEMENT_PROPERTY,
			static::TYPE_IBLOCK_ELEMENT_SEO,
			static::TYPE_IBLOCK_OFFER_FIELD,
			static::TYPE_IBLOCK_OFFER_PROPERTY,
			static::TYPE_IBLOCK_OFFER_SEO,
			static::TYPE_IBLOCK_PROPERTY_FEATURE,
			static::TYPE_IBLOCK_SECTION,
			static::TYPE_MARKET_PROPERTY,
			static::TYPE_CATALOG_PRICE,
			static::TYPE_CATALOG_PRICE_MATRIX,
			static::TYPE_CATALOG_PRODUCT,
			static::TYPE_CATALOG_STORE,
			static::TYPE_CURRENCY,
			static::TYPE_TEXT,
			static::TYPE_TEMPLATE,
			static::TYPE_FORMULA,
		];
	}
}