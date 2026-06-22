<?php
namespace Yandex\Market\Api\Campaigns\Model;

use Yandex\Market\Api;

class Business extends Api\Reference\Model
{
	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function getName()
	{
		return (string)$this->requireField('name');
	}
}