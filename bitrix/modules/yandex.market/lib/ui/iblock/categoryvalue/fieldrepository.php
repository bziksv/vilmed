<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Iblock\CategoryField;
use Yandex\Market\Ui\Iblock\CategoryProvider;

class FieldRepository
{
	use Concerns\HasOnceStatic;

	public static function iblockId($entityId)
	{
		if (preg_match('/^IBLOCK_(\d+)_SECTION$/', $entityId, $matches))
		{
			return (int)$matches[1];
		}

		throw new Main\ArgumentException("cant parse iblockId from {$entityId}");
	}

	public static function resetFieldCache()
	{
		static::clearOnceStatic('field');
	}

	public static function field($iblockId)
	{
		$iblockId = (int)$iblockId;

		if ($iblockId <= 0) { return null; }

		return static::onceStatic('field', [ $iblockId ], static function($iblockId) {
			global $USER_FIELD_MANAGER;

			foreach ($USER_FIELD_MANAGER->GetUserFields("IBLOCK_{$iblockId}_SECTION", 0, LANGUAGE_ID) as $field)
			{
				if ($field['USER_TYPE_ID'] === CategoryField::USER_TYPE)
				{
					$field['NAME'] = $field['LIST_COLUMN_LABEL'] ?: $field['LIST_FILTER_LABEL'] ?: $field['EDIT_FORM_LABEL'] ?: $field['FIELD_NAME'];

					return $field;
				}
			}

			return null;
		});
	}

	public static function fieldName($iblockId)
	{
		$field = self::field($iblockId);

		return ($field !== null ? $field['FIELD_NAME'] : null);
	}

	public static function sectionValue($iblockId, $sectionId, $fieldName = null)
	{
		global $USER_FIELD_MANAGER;

		$iblockId = (int)$iblockId;
		$sectionId = (int)$sectionId;

		if ($iblockId === 0 || $sectionId === 0) { return null; }

		$fieldName = $fieldName !== null ? $fieldName : self::fieldName($iblockId);

		if ($fieldName === null) { return null; }

		$fieldValue = $USER_FIELD_MANAGER->GetUserFieldValue("IBLOCK_{$iblockId}_SECTION", $fieldName, $sectionId);

		return $fieldValue ? CategoryProvider::decodeValue($fieldValue) : null;
	}

	public static function saveSection($iblockId, $sectionId, array $value = null)
	{
		Assert::positiveInteger($iblockId, 'iblockId');
		Assert::positiveInteger($sectionId, 'sectionId');

		$iblockId = (int)$iblockId;
		$sectionId = (int)$sectionId;
		$fieldName = self::fieldName($iblockId);

		Assert::notNull($fieldName, 'fieldName');

		$updateProvider = new \CIBlockSection();
		$updateProvider->Update($sectionId, [ $fieldName => $value ], false, false);
	}
}