<?php
namespace Yandex\Market\Trading\Campaign;

use Yandex\Market\Reference\Concerns;

class ModelPool
{
	use Concerns\HasOnceStatic;

	protected static $pool = [];

	/** @return Model */
	public static function getById($id)
	{
		$id = (int)$id;

		return self::onceStatic('getById', $id, static function($id) {
			return Model::loadById($id);
		});
	}
}