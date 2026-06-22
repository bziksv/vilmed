<?php
namespace Yandex\Market\Trading\Business;

use Yandex\Market\Api;

class ExternalSettings
{
	private $values;

	public static function fromApi(Api\Business\Settings\Model\Settings $apiSettings)
	{
		return new static([
			'ONLY_DEFAULT_PRICE' => $apiSettings->onlyDefaultPrice(),
		]);
	}

	public function __construct($values = null)
	{
		$this->setValues($values);
	}

	public function onlyDefaultPrice()
	{
		return (bool)$this->values['ONLY_DEFAULT_PRICE'];
	}

	public function setValues($values)
	{
		$this->values = is_array($values) ? $values : [];
	}

	public function getValues()
	{
		return $this->values;
	}
}