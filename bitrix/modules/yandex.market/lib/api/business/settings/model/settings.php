<?php
namespace Yandex\Market\Api\Business\Settings\Model;

use Yandex\Market\Api\Reference\Model;

class Settings extends Model
{
	public function getCurrency()
	{
		return (string)$this->requireField('currency');
	}

	public function onlyDefaultPrice()
	{
		return (bool)$this->getField('onlyDefaultPrice');
	}
}
