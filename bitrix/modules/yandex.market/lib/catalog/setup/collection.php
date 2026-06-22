<?php
namespace Yandex\Market\Catalog\Setup;

use Yandex\Market\Reference\Storage;

class Collection extends Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}
}