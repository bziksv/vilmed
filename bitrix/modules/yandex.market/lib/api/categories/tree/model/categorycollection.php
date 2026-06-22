<?php
namespace Yandex\Market\Api\Categories\Tree\Model;

use Yandex\Market\Api\Reference\Collection;

class CategoryCollection extends Collection
{
	public static function getItemReference()
	{
		return Category::class;
	}
}