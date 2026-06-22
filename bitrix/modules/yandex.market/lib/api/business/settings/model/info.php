<?php
namespace Yandex\Market\Api\Business\Settings\Model;

use Yandex\Market\Api\Reference\Model;

class Info extends Model
{
	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function getName()
	{
		return (string)$this->getField('name');
	}
}
