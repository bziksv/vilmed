<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Main;
use Bitrix\Iblock;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Iblock\CategoryProperty;

class PropertyRepository
{
	use Concerns\HasOnceStatic;

	public static function resetFieldCache()
	{
		static::clearOnceStatic('property');
	}

	public static function property($iblockId)
	{
		$iblockId = (int)$iblockId;

		if ($iblockId <= 0) { return null; }

		return static::onceStatic('property', [ $iblockId ], static function($iblockId) {
			if (!Main\Loader::includeModule('iblock')) { return null; }

			$property = Iblock\PropertyTable::getRow([
				'select' => [ 'ID', 'NAME', 'ACTIVE', 'MULTIPLE' ],
				'filter' => [
					'=IBLOCK_ID' => $iblockId,
					'=ACTIVE' => 'Y',
					'=USER_TYPE' => CategoryProperty::USER_TYPE,
				],
				'order' => [ 'SORT' => 'ASC', 'ID' => 'ASC' ],
			]);

			return $property;
		});
	}

	public static function propertyId($iblockId)
	{
		$property = self::property($iblockId);

		return ($property !== null ? (int)$property['ID'] : null);
	}

	public static function elementValue($iblockId, $elementId)
	{
		return ElementFetcher::property($iblockId, $elementId);
	}

	public static function saveElement($iblockId, $elementId, array $value = null)
	{
		Assert::positiveInteger($iblockId, 'iblockId');
		Assert::positiveInteger($elementId, 'elementId');

		$iblockId = (int)$iblockId;
		$elementId = (int)$elementId;
		$propertyId = self::propertyId($iblockId);

		Assert::notNull($propertyId, 'propertyId');

		\CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [ $propertyId => [ 'VALUE' => $value ] ]);
	}
}