<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Iblock;
use Bitrix\Main;
use Yandex\Market\Export\Entity\Iblock\Provider as IblockProvider;
use Yandex\Market\Ui\Iblock\CategoryProvider;

class ElementFetcher
{
	private static $select = [ 'ID', 'IBLOCK_SECTION_ID' ];
	private static $preloadedElements = [];
	private static $preloadedSkus = [];
	private static $preloadedProperties = [];

	public static function preload($iblockId, array $elementIds)
	{
		$catalogIblockId = IblockProvider::getCatalogIblockId($iblockId);

		self::$preloadedElements += self::fetchElements($elementIds);
		self::$preloadedProperties += self::fetchProperties($iblockId, $elementIds);

		if ($catalogIblockId !== null)
		{
			$skus = self::fetchSkus($iblockId, $elementIds);

			self::$preloadedSkus += $skus;
			self::preload($catalogIblockId, array_values(array_column($skus, 'ID', 'ID')));
		}
	}

	public static function release()
	{
		self::$preloadedElements = [];
		self::$preloadedProperties = [];
		self::$preloadedSkus = [];
	}

	public static function element($elementId, array $select)
	{
		self::testSelect($select);

		$elementId = (int)$elementId;

		if ($elementId <= 0) { return null; }

		if (isset(self::$preloadedElements[$elementId]) || array_key_exists($elementId, self::$preloadedElements))
		{
			return self::$preloadedElements[$elementId];
		}

		$rows = self::fetchElements([ $elementId ]);

		return $rows[$elementId];
	}

	public static function sku($iblockId, $elementId)
	{
		$elementId = (int)$elementId;

		if ($elementId <= 0) { return null; }

		$skus = isset(self::$preloadedSkus[$elementId])
			? self::$preloadedSkus
			: self::fetchSkus($iblockId, [ $elementId ]);

		return !empty($skus[$elementId]) ? $skus[$elementId] : null;
	}

	public static function property($iblockId, $elementId)
	{
		$elementId = (int)$elementId;

		if ($elementId <= 0) { return null; }

		if (isset(self::$preloadedProperties[$elementId]) || array_key_exists($elementId, self::$preloadedProperties))
		{
			return self::$preloadedProperties[$elementId];
		}

		$properties = self::fetchProperties($iblockId, [ $elementId ]);

		return $properties[$elementId];
	}

	private static function fetchElements(array $elementIds)
	{
		if (empty($elementIds) || !Main\Loader::includeModule('iblock')) { return []; }

		$rows = array_fill_keys($elementIds, null);

		$query = Iblock\ElementTable::getList([
			'filter' => [ '=ID' => $elementIds ],
			'select' => self::$select,
		]);

		while ($row = $query->fetch())
		{
			$rows[$row['ID']] = $row;
		}

		return $rows;
	}

	private static function testSelect(array $select)
	{
		$diff = array_diff($select, self::$select);

		if (count($diff) > 0)
		{
			$missing = implode(', ', $diff);

			throw new Main\ArgumentException("{$missing} missing in allowed select");
		}
	}

	private static function fetchProperties($iblockId, array $elementIds)
	{
		if (empty($elementIds) || !Main\Loader::includeModule('iblock')) { return []; }

		$propertyId = PropertyRepository::propertyId($iblockId);

		if ($propertyId === null) { return []; }

		$result = array_fill_keys($elementIds, null);
		$query = \CIBlockElement::GetPropertyValues($iblockId, [ '=ID' => $elementIds ], false, [ 'ID' => $propertyId ]);

		while ($row = $query->Fetch())
		{
			if (empty($row[$propertyId])) { continue; }

			$result[$row['IBLOCK_ELEMENT_ID']] = CategoryProvider::decodeValue($row[$propertyId]);
		}

		return $result;
	}

	private static function fetchSkus($iblockId, array $elementIds)
	{
		if (empty($elementIds) || !Main\Loader::includeModule('catalog')) { return []; }

		return \CCatalogSKU::getProductList($elementIds, $iblockId);
	}
}