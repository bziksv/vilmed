<?php
namespace Yandex\Market\Api\Categories\Tree\Model;

use Yandex\Market\Api\Reference\Model;

class Category extends Model
{
	public function getId()
	{
		return (int)$this->requireField('id');
	}

	public function getName()
	{
		return (string)$this->requireField('name');
	}

	public function getChildren()
	{
		return $this->getCollection('children', CategoryCollection::class);
	}
}