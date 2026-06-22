<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Iblock;
use Bitrix\Main;
use Yandex\Market\Ui\Iblock\CategoryProperty;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Utils\PhpSerializer;

class PropertyDefault implements CategoryValue
{
	private $iblockId;
	private $propertyId;

	public function __construct($iblockId, $propertyId = null)
	{
		$this->iblockId = (int)$iblockId;
		$this->propertyId = $propertyId;
	}

	public function value()
	{
		$property = $this->property();

		if (empty($property['USER_TYPE_SETTINGS']['DEFAULT_VALUE']))
		{
			return null;
		}

		return CategoryProvider::decodeValue($property['USER_TYPE_SETTINGS']['DEFAULT_VALUE']);
	}

	public function save(array $value = null)
	{
		$property = $this->property();

		if ($property === null) { return; }

		$property['USER_TYPE_SETTINGS']['DEFAULT_VALUE'] = $value;
		$settings = CategoryProperty::prepareSettings($property);

		Iblock\PropertyTable::update($property['ID'], [
			'USER_TYPE_SETTINGS' => PhpSerializer::encode($settings),
		]);
	}

	private function property()
	{
		$propertyId = (int)$this->propertyId();

		if ($propertyId <= 0 || !Main\Loader::includeModule('iblock'))
		{
			return null;
		}

		$property = Iblock\PropertyTable::getRow([
			'filter' => [ '=ID' => $propertyId ],
			'select' => [ 'ID', 'USER_TYPE_SETTINGS' ],
		]);

		if ($property === null) { return null; }

		if (is_string($property['USER_TYPE_SETTINGS']) && $property['USER_TYPE_SETTINGS'] !== '')
		{
			$property['USER_TYPE_SETTINGS'] = PhpSerializer::decode($property['USER_TYPE_SETTINGS']);
		}

		if (!is_array($property['USER_TYPE_SETTINGS']))
		{
			$property['USER_TYPE_SETTINGS'] = [];
		}

		return $property;
	}

	public function parent()
	{
		return new FieldDefault($this->iblockId);
	}

	private function propertyId()
	{
		if ($this->propertyId !== null) { return (int)$this->propertyId; }

		return PropertyRepository::propertyId($this->iblockId);
	}
}