<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs\Model\Order;

use Yandex\Market;

class Buyer extends Market\Trading\Service\Marketplace\Model\Order\Buyer
{
	use Market\Reference\Concerns\HasMessage;

	public static function getMeaningfulFields()
	{
		return array_merge([
			'LAST_NAME',
			'FIRST_NAME',
			'MIDDLE_NAME',
			'PHONE',
		], parent::getMeaningfulFields());
	}

	public static function getMeaningfulFieldTitle($fieldName)
	{
		return self::getMessage('FIELD_' . $fieldName, null, parent::getMeaningfulFieldTitle($fieldName));
	}

	public function isPlaceholder()
	{
		return $this->getField('type') === static::TYPE_PERSON && !$this->hasField('id');
	}

	public function getPhone()
	{
		return $this->getField('phone');
	}

	public function getLastName()
	{
		return $this->getField('lastName');
	}

	public function getFirstName()
	{
		return $this->getField('firstName');
	}

	public function getMiddleName()
	{
		return $this->getField('middleName');
	}

	public function getEmail()
	{
		$value = $this->getField('email');

		if ($value === 'noreply-market@support.yandex.ru')
		{
			return null;
		}

		return $value;
	}

	public function getMeaningfulValues()
	{
		return array_filter([
			'ID' => $this->getId(),
			'LAST_NAME' => $this->getLastName(),
			'FIRST_NAME' => $this->getFirstName(),
			'MIDDLE_NAME' => $this->getMiddleName(),
		] + parent::getMeaningfulValues());
	}

	public function getCompatibleValues()
	{
		return array_filter([
			'PHONE' => $this->getPhone(),
		]);
	}
}