<?php
namespace Yandex\Market\Trading\Business;

use Yandex\Market;

/** @method Model getItemById($id) */
class Collection extends Market\Reference\Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}
}