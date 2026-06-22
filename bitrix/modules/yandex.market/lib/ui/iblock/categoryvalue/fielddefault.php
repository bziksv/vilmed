<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Main;
use Yandex\Market\Ui\Iblock\CategoryProvider;

class FieldDefault implements CategoryValue
{
	private $iblockId;
	private $fieldName;

	public function __construct($iblockId, $fieldName = null)
	{
		$this->iblockId = (int)$iblockId;
		$this->fieldName = $fieldName;
	}

	public function value()
	{
		$userField = $this->userField();

		if (!isset($userField['SETTINGS']['DEFAULT_VALUE']))
		{
			return null;
		}

		return CategoryProvider::decodeValue($userField['SETTINGS']['DEFAULT_VALUE']);
	}

	public function save(array $value = null)
	{
		$userField = $this->userField();

		if ($userField === null) { return; }

		$settings = is_array($userField['SETTINGS']) ? $userField['SETTINGS'] : [];
		$settings['DEFAULT_VALUE'] = $value;

		(new \CUserTypeEntity())->Update($userField['ID'], [ 'SETTINGS' => $settings ]);
	}

	public function parent()
	{
		return null;
	}

	private function userField()
	{
		global $USER_FIELD_MANAGER;

		$fieldName = $this->fieldName();

		if ($fieldName === null) { return null; }

		$userFields = $USER_FIELD_MANAGER->GetUserFields("IBLOCK_{$this->iblockId}_SECTION");

		if (!isset($userFields[$fieldName])) { return null; }

		return $userFields[$fieldName];
	}

	private function fieldName()
	{
		if ($this->fieldName !== null) { return $this->fieldName; }

		return FieldRepository::fieldName($this->iblockId);
	}
}