<?php
namespace Yandex\Market\Api\Model;

use Yandex\Market;

class Region extends Market\Api\Reference\Model
{
	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function getName()
	{
		return (string)$this->requireField('name');
	}

	public function getType()
	{
		return (string)$this->requireField('type');
	}

	public function getParent()
	{
		return $this->getModel('parent', static::class);
	}
}